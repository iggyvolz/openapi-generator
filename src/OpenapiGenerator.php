<?php

namespace Iggyvolz\OpenapiGenerator;

use iggyvolz\classgen\NamespacedClassGenerator;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpNamespace;
use Nette\Utils\Json;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Stringable;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use Nette\Utils\FileSystem;
use Symfony\Component\Yaml\Yaml;

abstract class OpenapiGenerator extends NamespacedClassGenerator
{
    private ?array $contents = null;
    private function generateClient(ClassType $class): void
    {
        $constructor = $class->addMethod("__construct");
        $constructor->addPromotedParameter("httpClient")->setPrivate()->setType(ClientInterface::class);
        $constructor->addPromotedParameter("requestFactory")->setPrivate()->setType(RequestFactoryInterface::class);
        $constructor->addPromotedParameter("endpoint")->setPrivate()->setType("string");
        $value = Yaml::parse(FileSystem::read($this->getFile()));
        foreach($value["paths"] as $methodPath => $pathItem) {
            foreach($pathItem as $httpMethod => $operation) {
                $operationId = $operation["operationId"];
                $method = $class->addMethod($operationId);
                $method->addBody('$response = $this->httpClient->sendRequest($this->requestFactory->createRequest(');
                $method->addBody("\t\"$httpMethod\",");
                foreach($operation["parameters"] ?? [] as $parameterDescription) {
                    $parameter = $method->addParameter($parameterDescription["name"]);
                }
                $methodPathRepl = str_replace("{", '${', $methodPath);
                $method->addBody("\t\$this->endpoint . \"$methodPathRepl\",");
                $method->addBody(')->withHeader("Accept", "application/json"));');
                $method->addBody('switch($response->getStatusCode()) {');
                $handledDefault = false;
                foreach($operation["responses"] as $responseCode => $response) {
                    if($responseCode === "default") {
                        $handledDefault = true;
                        $method->addBody("\tdefault:");
                    } else {
                        $method->addBody("\tcase $responseCode:");
                    }
                    $method->addBody("\t\tswitch((\$response->getHeader('content-type')??[])[0]??null) {");
                    if(array_key_exists("content", $response)) {
                        foreach ($response["content"] as $contentType => $content) {
                            $method->addBody("\t\t\tcase '$contentType':");
                            $method->addBody("\t\t\t\t\$responseBody = \$response->getBody()->getContents();");
                            if($contentType === "application/json") {
                                $method->addBody("\t\t\t\t/* " . json_encode($content) . " */");
                                $method->addBody("\t\t\t\t\$responseBody = \Nette\Utils\Json::decode(\$responseBody, \Nette\Utils\Json::FORCE_ARRAY);");
                                $method->addBody("\t\t\t\treturn \$responseBody;");
                            } else {
                                $method->addBody("\t\t\t\t/* " . json_encode($content) . " */");
                                $method->addBody("\t\t\t\tthrow new \RuntimeException('Could not handle $contentType');");
                            }
                        }
                    }
                    $method->addBody("\t\t}");
                }
                if(!$handledDefault) {
                    $method->addBody("\tdefault:");
                    $method->addBody("\t\tthrow new \RuntimeException(\"Unexpected response code \" . \$response->getStatusCode());");
                }
                $method->addBody('}');
            }
        }
    }
    private function generateServer(ClassType $class): void
    {
        $class->setAbstract();
        $class->addImplement(RequestHandlerInterface::class);
        $constructor = $class->addMethod("__construct");
        $constructor->addPromotedParameter("baseUrl")->setPrivate()->setType('string');
        $constructor->addPromotedParameter("responseFactory")->setPrivate()->setType(ResponseFactoryInterface::class);
        $constructor->addPromotedParameter("streamFactory")->setPrivate()->setType(StreamFactoryInterface::class);
        $handle = $class->addMethod("handle");
        $handle->addParameter("request")->setType(ServerRequestInterface::class);
        $handle->setReturnType(ResponseInterface::class);
        $value = Yaml::parse(FileSystem::read($this->getFile()));
        $handle->addBody("if(str_starts_with(\$request->getUri()->getPath(), \$this->baseUrl)) {");
        $handle->addBody("\t\$path = substr(\$request->getUri()->getPath(), strlen(\$this->baseUrl));");
        $handle->addBody("} else {");
        $handle->addBody("\treturn \$this->responseFactory->createResponse(404)->withBody(\$this->streamFactory->createStream(\"URL not found: \" . \$request->getUri()->getPath()));");
        $handle->addBody("\tthrow new \\" . RuntimeException::class . "('URL not found: ' . \$request->getUri()->getPath());");
        $handle->addBody("}");
        foreach($value["paths"] as $methodPath => $pathItem) {
            $handle->addBody("if(preg_match(".var_export($this->pregify($methodPath), true).", \$path, \$matches)) {");
            foreach($pathItem as $httpMethod => $operation) {
                $handle->addBody("\tif(\strtolower(\$request->getMethod()) === " . var_export(strtolower($httpMethod), true) . ") {");
//                $handle->addBody("\t\t/* ". json_encode($operation["responses"])." */");
                $handle->addBody("\t\t" . $this->generateServerOperation($class->addMethod($operation["operationId"]), $operation, $methodPath));
                $handle->addBody("\treturn \$this->responseFactory->createResponse(200);");
                $handle->addBody("\t}");
            }
            $handle->addBody("}");
        }
        $handle->addBody("return \$this->responseFactory->createResponse(404)->withBody(\$this->streamFactory->createStream(\"URL not found: \" . \$request->getUri()->getPath()));");
    }
    // Extract generation out to one method since multiple classes will need to be generated
    private function doGenerate(): void
    {
        $this->contents = [];
        $client = new PhpFile();
        $client->setStrictTypes();
        $this->generateClient($client->addNamespace($this->getNamespace())->addClass("APIClient"));


        $server = new PhpFile();
        $server->setStrictTypes();
        $this->generateServer($server->addNamespace($this->getNamespace())->addClass("APIServer"));
        $printer = new PsrPrinter();
        $this->contents[$this->getNamespace() . "\\APIClient"] = $printer->printFile($client);
        $this->contents[$this->getNamespace() . "\\APIServer"] = $printer->printFile($server);

        // Generate server
    }

    protected function generate(string $class): string|Stringable
    {
        if(is_null($this->contents)) {
            $this->doGenerate();
        }
        return $this->contents[$class] ?? "";
    }
    protected abstract function getFile(): string;
    protected function isValid(string $class): bool
    {
        if(is_null($this->contents)) {
            $this->doGenerate();
        }
        return parent::isValid($class) && array_key_exists($class, $this->contents);
    }

    private function pregify(string $path): string
    {
        return preg_replace("@\\\\{([a-zA-Z0-9]+)\\\\}@", "(?<$1>[^/+])", "@" . preg_quote($path) . "@");
    }

    private function generateServerOperation(Method $method, array $operation, string $path): string
    {
        preg_match_all("@{([a-zA-Z0-9]+)}@", $path, $matches);
        $params = $matches[1];
        $callingCode = "\$this->" . $operation["operationId"] . "(";
        foreach($params as $param) {
            $method->addParameter($param)->setType("string");
            $callingCode .= "\$$param, ";
        }
        $callingCode .= ");";
        $method->setAbstract();
        if(array_key_exists("requestBody", $operation) && array_key_exists("application/json", $operation["requestBody"]["content"])) {
            // TODO handle more than JSON here
            // Maybe merge multiple content types
            $required = boolval($operation["requestBody"]["required"] ?? false);
            $schema = $operation["requestBody"]["content"]["application/json"]["schema"];
            assert(is_array($schema));
            list($type, $docblockType) = $this->generateSchema($schema);
            $parameter = $method->addParameter("body");
            $parameter->setType($type);
            if(!is_null($docblockType)) {
                $method->addComment("@param $docblockType \$body");
            }
            $parameter->setNullable(!$required);
        }
        if(array_key_exists("responses", $operation)) {
            // Create abstract response class
            $abstractResponseClassName = ucfirst($method->getName()."Response");
            $method->setReturnType($this->getNamespace() . "\\responses\\" . $abstractResponseClassName);
            $abstractResponseClassFile = new PhpFile();
            $abstractResponseClass = $abstractResponseClassFile->addNamespace($this->getNamespace(). "\\responses")->addClass($abstractResponseClassName);
            $abstractResponseClass->setAbstract();
            $constructor = $abstractResponseClass->addMethod("__construct");
            $constructor->addPromotedParameter("description")->setReadOnly()->setType("string");
            $constructor->addPromotedParameter("responseCode")->setReadOnly()->setType("int");
            $this->contents[$this->getNamespace() . "\\responses\\" . $abstractResponseClassName] = (new PsrPrinter())->printFile($abstractResponseClassFile);
            class_exists($this->getNamespace() . "\\responses\\" . $abstractResponseClassName);
            foreach($operation["responses"] as $code => $response) {
                $responseClassName = $abstractResponseClassName . ucfirst($code);
                $responseClassFile = new PhpFile();
                $responseClass = $responseClassFile->addNamespace($this->getNamespace())->addClass($responseClassName);
                $responseClass->setExtends($this->getNamespace() . "\\responses\\" . $abstractResponseClassName);
                $responseClass->addConstant("DESCRIPTION", $response["description"] ?? "");
                $constructor = $responseClass->addMethod("__construct");
                $constructor->addComment(Yaml::dump($response));
                $constructor->addBody("parent::__construct(");
                $constructor->addBody("\tself::DESCRIPTION,");
                if($code === "default") {
                    $constructor->addParameter("code")->setType("int");
                    $constructor->addBody("\t\$code,");
                } else {
                    $constructor->addBody("\t$code,");
                }
                $constructor->addBody(");");
                if(array_key_exists("content", $response) && array_key_exists("application/json", $response["content"])) {
                    [$type, $docblockType] = $this->generateSchema($response["content"]["application/json"]["schema"]);
                    $constructor->addPromotedParameter("content")->setType($type);
                    if(!is_null($docblockType)) {
                        $constructor->addComment("@param $docblockType \$content");
                    }
                }
                $this->contents[$this->getNamespace() . "\\responses\\" . $responseClassName] = (new PsrPrinter())->printFile($responseClassFile);
                class_exists($this->getNamespace() . "\\responses\\" . $responseClassName);
            }
        } else {
            $method->setReturnType("void");
        }
        return $callingCode;
    }

    private function generateRef(string $type): void
    {
        if(array_key_exists($type, $this->contents)) return;
        $file = new PhpFile();
        $ns = explode("\\", $type);
        $className = array_pop($ns);
        $class = $file->addNamespace(implode("\\", $ns))->addClass($className);
        $pathWithoutLeadingNamespace = explode("\\", substr($type, strlen($this->getNamespace()) + 1));

        $schema = Yaml::parse(FileSystem::read($this->getFile()));
        foreach($pathWithoutLeadingNamespace as $path) {
            $schema = $schema[$path];
        }
        $constructor = $class->addMethod("__construct");
        foreach($schema["properties"] as $name => $property) {
            $class->addComment("$name:\n\t" . str_replace("\n", "\n\t", Yaml::dump($property)));
            $constructor->addPromotedParameter($name)->setPublic()->setReadOnly()->setType(match($property["type"] ?? null) {
                "integer" => "int",
                "string" => "string",
                "boolean" => "bool",
                default => "mixed",
            });
        }
        $this->contents[$type] = (new PsrPrinter())->printFile($file);
        self::autoload($type);
    }

    /**
     * @param array $schema
     * @return array{0:string,1:string}
     */
    private function generateSchema(array $schema): array
    {
        $docblockType = null;
        if (array_key_exists('$ref', $schema)) {
            $type = $schema['$ref'];
            $type = str_replace("#", $this->getNamespace(), $type);
            $type = preg_replace("@/@", "\\", $type);
            $this->generateRef($type);
        } elseif ($schema["type"] === "array") {
            $listType = $schema["items"]['$ref'];
            $listType = str_replace("#", $this->getNamespace(), $listType);
            $listType = preg_replace("@/@", "\\", $listType);
            $type = "array";
            $docblockType = "list<$listType>";
            $this->generateRef($listType);
        } else {
            $type = "mixed";
        }
        return [$type, $docblockType];
    }
}