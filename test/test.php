<?php

use GuzzleHttp\Client;
use Nyholm\Psr7\Factory\Psr17Factory;

require_once __DIR__ . "/../vendor/autoload.php";
require_once "PetStoreGenerator.php";
PetStoreGenerator::register();
var_dump(class_exists(PetStore\API::class));

$petStore = new PetStore\API(
    new Client(),
    new Psr17Factory(),
    "https://petstore3.swagger.io/api/v3"
);
var_dump($petStore->getInventory());