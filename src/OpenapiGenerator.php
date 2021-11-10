<?php

namespace Iggyvolz\OpenapiGenerator;

use iggyvolz\classgen\NamespacedClassGenerator;
use Stringable;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use Nette\Utils\FileSystem;
use Symfony\Component\Yaml\Yaml;

abstract class OpenapiGenerator extends NamespacedClassGenerator
{
    private ?array $contents = null;
    // Extract generation out to one method since multiple classes will need to be generated
    private function doGenerate(): void
    {
        $this->contents = [];
        $file = new PhpFile();
        $file->setStrictTypes();
        $namespace = $file->addNamespace($this->getNamespace());
        $class = $namespace->addClass("API");
        $constructor = $class->addMethod("__construct");
        $constructor->addPromotedParameter("httpClient")->setPrivate()->setType(\Psr\Http\Client\ClientInterface::class);
        $constructor->addPromotedParameter("requestFactory")->setPrivate()->setType(\Psr\Http\Message\RequestFactoryInterface::class);
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
        $printer = new PsrPrinter();
        $this->contents[$this->getNamespace() . "\\API"] = $printer->printFile($file); // 4 spaces indentation
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
}