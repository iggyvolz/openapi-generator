<?php

namespace Iggyvolz\OpenapiGenerator;

use iggyvolz\classgen\NamespacedClassGenerator;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpNamespace;
use Nette\Utils\Json;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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
                foreach($operation["responses"] as $responseCode => $response) {
                    $method->addBody($responseCode === "default" ? "\tdefault:" : "\tcase $responseCode:");
                    $method->addBody("\t\tswitch(\$response->getHeader('content-type')[0]) {");
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
                $method->addBody('}');
            }
        }
    }
    private function generateServer(ClassType $class): void
    {
        $class->setAbstract();
        $class->addImplement(RequestHandlerInterface::class);
        $handle = $class->addMethod("handle");
        $handle->addParameter("request")->setType(ServerRequestInterface::class);
        $handle->setReturnType(ResponseInterface::class);
        $value = Yaml::parse(FileSystem::read($this->getFile()));
        foreach($value["paths"] as $methodPath => $pathItem) {
            $handle->addBody("if(preg_match(".var_export($this->pregify($methodPath), true).", \$request->getUri()->getPath(), \$matches)) {");
            foreach($pathItem as $httpMethod => $operation) {
                $handle->addBody("\tif(\strtolower(\$request->getMethod()) === " . var_export(strtolower($httpMethod), true) . ") {");
                $handle->addBody("\t\t" . $this->generateServerOperation($class->addMethod($operation["operationId"]), $operation, $methodPath));
                $handle->addBody("\t}");
            }
            $handle->addBody("}");
        }
        $handle->addBody("throw new \\" . RuntimeException::class . "('Not implemented');");
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
            $required = $operation["requestBody"]["required"] ?? false;
            $schema = $operation["requestBody"]["content"]["application/json"]["schema"];
            $body = $method->addParameter("body");
            if(array_key_exists('$ref', $schema)) {
                $type = $schema['$ref'];
                $type = str_replace("#", $this->getNamespace(), $type);
                $type = preg_replace("@/@", "\\", $type);
                $this->generateSchema($type);
                $body->setType($type);
            } elseif($schema["type"] === "array") {
                $body->setType('array');
                $type = $schema["items"]['$ref'];
                $type = str_replace("#", $this->getNamespace(), $type);
                $type = preg_replace("@/@", "\\", $type);
                $this->generateSchema($type);
                $method->addComment("@param list<$type>" . ($required ? "" : "|null") . " \$body");
            }
            if(!$required) {
                $body->setNullable();
            }
//            $method->addComment(Yaml::dump($schema));
        }
        return $callingCode;
    }

    private function generateSchema(string $type): void
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
            $constructor->addPromotedParameter($name)->setPublic()->setReadOnly()->setType(match($property["type"]) {
                "integer" => "int",
                "string" => "string",
                "boolean" => "bool",
                default => "mixed",
            });
        }
        $this->contents[$type] = (new PsrPrinter())->printFile($file);
        self::autoload($type);
    }
}