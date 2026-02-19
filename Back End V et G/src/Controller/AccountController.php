<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use App\Repository\UserRepository;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api/account', name: 'app_api_account_')]
class AccountController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private UserRepository $repository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    

    // ✅ ME (via apiToken)
    #[Route('/me', name: 'me', methods: ['GET'])]
    #[OA\Get(
        path: '/api/account/me',
        summary: "Afficher mon compte (via apiToken)",
        parameters: [
            new OA\Parameter(
                name: 'X-AUTH-TOKEN',
                in: 'header',
                required: true,
                description: 'apiToken de l’utilisateur',
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Compte trouvé"),
            new OA\Response(response: 401, description: "Token manquant ou invalide"),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-AUTH-TOKEN');

        if (!$token) {
            return new JsonResponse(['error' => 'Token manquant (X-AUTH-TOKEN)'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->repository->findOneBy(['apiToken' => $token]);

        if (!$user) {
            return new JsonResponse(['error' => 'Token invalide'], Response::HTTP_UNAUTHORIZED);
        }

        $responseData = json_encode([
    'id' => $user->getId(),
    'email' => $user->getEmail(),
    'roles' => $user->getRoles(),
    'firstName' => $user->getFirstName(),
    'lastName' => $user->getLastName(),
    'gsm' => $user->getGsm(),
    'postalAddress' => $user->getPostalAddress(),
    'allergies' => method_exists($user, 'getAllergies') ? $user->getAllergies():null ,
    'createdAt' => $user->getCreatedAt()?->format(DATE_ATOM),
    'updatedAt' => $user->getUpdatedAt()?->format(DATE_ATOM),
]);


        return new JsonResponse($responseData, Response::HTTP_OK, [], true);
    }

    // ✅ UPDATE ME (via apiToken)
    #[Route('/me', name: 'edit_me', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/account/me',
        summary: "Modifier mon compte (via apiToken)",
        parameters: [
            new OA\Parameter(
                name: 'X-AUTH-TOKEN',
                in: 'header',
                required: true,
                description: 'apiToken de l’utilisateur',
                schema: new OA\Schema(type: 'string')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Champs modifiables: email, password, roles",
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'new@mail.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'NouveauMotDePasse123!'),
                    new OA\Property(
                        property: 'roles',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['ROLE_CLIENT']
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: "Compte modifié"),
            new OA\Response(response: 401, description: "Token manquant ou invalide"),
        ]
    )]
    public function editMe(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-AUTH-TOKEN');

        if (!$token) {
            return new JsonResponse(['error' => 'Token manquant (X-AUTH-TOKEN)'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->repository->findOneBy(['apiToken' => $token]);

        if (!$user) {
            return new JsonResponse(['error' => 'Token invalide'], Response::HTTP_UNAUTHORIZED);
        }

        // comme RestaurantController (populate via serializer)
        $this->serializer->deserialize(
            $request->getContent(),
            User::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $user]
        );

        // si password envoyé => on hash
        $payload = json_decode($request->getContent(), true);
        if (is_array($payload) && !empty($payload['password'])) {
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $payload['password'])
            );
        }

        $user->setUpdatedAt(new DateTimeImmutable());

        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    // ✅ DELETE ME (via apiToken)
    #[Route('/me', name: 'delete_me', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/account/me',
        summary: "Supprimer mon compte (via apiToken)",
        parameters: [
            new OA\Parameter(
                name: 'X-AUTH-TOKEN',
                in: 'header',
                required: true,
                description: 'apiToken de l’utilisateur',
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(response: 204, description: "Compte supprimé"),
            new OA\Response(response: 401, description: "Token manquant ou invalide"),
        ]
    )]
    public function deleteMe(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-AUTH-TOKEN');

        if (!$token) {
            return new JsonResponse(['error' => 'Token manquant (X-AUTH-TOKEN)'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->repository->findOneBy(['apiToken' => $token]);

        if (!$user) {
            return new JsonResponse(['error' => 'Token invalide'], Response::HTTP_UNAUTHORIZED);
        }

        $this->manager->remove($user);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
