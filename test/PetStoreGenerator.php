<?php

use Iggyvolz\OpenapiGenerator\OpenapiGenerator;

class PetStoreGenerator extends OpenapiGenerator
{

    protected function getFile(): string
    {
        return __DIR__ . "/petstore.yaml";
    }

    protected function getNamespace(): string
    {
        return "PetStore";
    }
}