<?php

class PetStoreGenerator extends \Iggyvolz\OpenapiGenerator\OpenapiGenerator
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