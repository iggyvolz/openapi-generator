<?php

use GuzzleHttp\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PetStore\APIServer;
use PetStore\components\schemas\Order;
use PetStore\components\schemas\Pet;
use PetStore\components\schemas\User;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/PetStoreGenerator.php";
require_once __DIR__ . "/TestTransformer.php";
PetStoreGenerator::register();
var_dump(class_exists(PetStore\APIClient::class));
var_dump(class_exists(PetStore\APIServer::class));

$apiServer = new class extends APIServer
{
    public function updatePet(Pet $body)
    {
        // TODO: Implement updatePet() method.
    }

    public function addPet(Pet $body)
    {
        // TODO: Implement addPet() method.
    }

    public function findPetsByStatus()
    {
        // TODO: Implement findPetsByStatus() method.
    }

    public function findPetsByTags()
    {
        // TODO: Implement findPetsByTags() method.
    }

    public function getPetById(string $petId)
    {
        // TODO: Implement getPetById() method.
    }

    public function updatePetWithForm(string $petId)
    {
        // TODO: Implement updatePetWithForm() method.
    }

    public function deletePet(string $petId)
    {
        // TODO: Implement deletePet() method.
    }

    public function uploadFile(string $petId)
    {
        // TODO: Implement uploadFile() method.
    }

    public function getInventory()
    {
        // TODO: Implement getInventory() method.
    }

    public function placeOrder(?Order $body)
    {
        // TODO: Implement placeOrder() method.
    }

    public function getOrderById(string $orderId)
    {
        // TODO: Implement getOrderById() method.
    }

    public function deleteOrder(string $orderId)
    {
        // TODO: Implement deleteOrder() method.
    }

    public function createUser(?User $body)
    {
        // TODO: Implement createUser() method.
    }

    /**
     * @param list<User>|null $body
     */
    public function createUsersWithListInput(?array $body)
    {
        // TODO: Implement createUsersWithListInput() method.
    }

    public function loginUser()
    {
        // TODO: Implement loginUser() method.
    }

    public function logoutUser()
    {
        // TODO: Implement logoutUser() method.
    }

    public function getUserByName(string $username)
    {
        // TODO: Implement getUserByName() method.
    }

    public function updateUser(string $username, ?User $body)
    {
        // TODO: Implement updateUser() method.
    }

    public function deleteUser(string $username)
    {
        // TODO: Implement deleteUser() method.
    }
};

$petStore = new PetStore\APIClient(
    new class implements ClientInterface
    {
        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            global $apiServer;
            return $apiServer->handle(new TestTransformer($request));
        }
    },
    new Psr17Factory(),
    "https://petstore3.swagger.io/api/v3"
);
var_dump($petStore->getInventory());