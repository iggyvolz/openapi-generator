<?php

use iggyvolz\classgen\ClassGenerator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PetStore\APIServer;
use PetStore\components\schemas;
use PetStore\responses;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/PetStoreGenerator.php";
require_once __DIR__ . "/TestTransformer.php";
//ClassGenerator::setMode(ClassGenerator::MODE_PRODUCTION);
PetStoreGenerator::register();
//spl_autoload(PetStore\APIClient::class);
//spl_autoload(PetStore\APIServer::class);
$psr17Factory = new Psr17Factory();
$apiServer = new class("/api/v3",$psr17Factory, $psr17Factory) extends APIServer
{
    public function updatePet(schemas\Pet $body): responses\UpdatePetResponse
    {
        // TODO: Implement updatePet() method.
    }

    public function addPet(schemas\Pet $body): responses\AddPetResponse
    {
        // TODO: Implement addPet() method.
    }

    public function findPetsByStatus(): responses\FindPetsByStatusResponse
    {
        // TODO: Implement findPetsByStatus() method.
    }

    public function findPetsByTags(): responses\FindPetsByTagsResponse
    {
        // TODO: Implement findPetsByTags() method.
    }

    public function getPetById(string $petId): responses\GetPetByIdResponse
    {
        // TODO: Implement getPetById() method.
    }

    public function updatePetWithForm(string $petId): responses\UpdatePetWithFormResponse
    {
        // TODO: Implement updatePetWithForm() method.
    }

    public function deletePet(string $petId): responses\DeletePetResponse
    {
        // TODO: Implement deletePet() method.
    }

    public function uploadFile(string $petId): responses\UploadFileResponse
    {
        // TODO: Implement uploadFile() method.
    }

    public function getInventory(): responses\GetInventoryResponse
    {
    }

    public function placeOrder(?schemas\Order $body): responses\PlaceOrderResponse
    {
        // TODO: Implement placeOrder() method.
    }

    public function getOrderById(string $orderId): responses\GetOrderByIdResponse
    {
        // TODO: Implement getOrderById() method.
    }

    public function deleteOrder(string $orderId): responses\DeleteOrderResponse
    {
        // TODO: Implement deleteOrder() method.
    }

    public function createUser(?schemas\User $body): responses\CreateUserResponse
    {
        // TODO: Implement createUser() method.
    }

    /**
     * @param list<schemas\User>|null $body
     */
    public function createUsersWithListInput(?array $body): responses\CreateUsersWithListInputResponse
    {
        // TODO: Implement createUsersWithListInput() method.
    }

    public function loginUser(): responses\LoginUserResponse
    {
        // TODO: Implement loginUser() method.
    }

    public function logoutUser(): responses\LogoutUserResponse
    {
        return new \PetStore\LogoutUserResponseDefault(200);
    }

    public function getUserByName(string $username): responses\GetUserByNameResponse
    {
        // TODO: Implement getUserByName() method.
    }

    public function updateUser(string $username, ?schemas\User $body): responses\UpdateUserResponse
    {
        // TODO: Implement updateUser() method.
    }

    public function deleteUser(string $username): responses\DeleteUserResponse
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
$petStore->logoutUser();