<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

use App\Entity\User;
use App\Repository\UserRepository;

#[OA\Tag(name: "Auth")]
#[Route('/api', name: 'app_api_')]
class SecurityController extends AbstractController
{
    public function __construct(private EntityManagerInterface $manager) {}

    #[Route('/registration', name: 'registration', methods: ['POST'])]
    #[OA\Post(
        path: '/api/registration',
        summary: "Inscription utilisateur",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['email','password','firstName','lastName','gsm','postalAddress'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'user@mail.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'MotDePasse123!'),
                    new OA\Property(property: 'firstName', type: 'string', example: 'Julie'),
                    new OA\Property(property: 'lastName', type: 'string', example: 'Martin'),
                    new OA\Property(property: 'gsm', type: 'string', example: '+33612345678'),
                    new OA\Property(property: 'postalAddress', type: 'string', example: '10 rue Victor Hugo, 33000 Bordeaux'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Compte créé"),
            new OA\Response(response: 409, description: "Email déjà utilisé"),
            new OA\Response(response: 422, description: "Données invalides"),
        ]
    )]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface $mailer,
        UserRepository $userRepository
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['email','password','firstName','lastName','gsm','postalAddress'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['message' => 'Champs obligatoires manquants'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $email = strtolower(trim((string)$data['email']));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['message' => 'Email invalide'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($userRepository->findOneBy(['email' => $email])) {
            return new JsonResponse(['message' => 'Email déjà utilisé'], Response::HTTP_CONFLICT);
        }

        $passwordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{10,}$/';
        if (!preg_match($passwordRegex, (string)$data['password'])) {
            return new JsonResponse(['message' => 'Mot de passe trop faible'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $gsm = preg_replace('/\s+/', '', (string)$data['gsm']);
        if (!preg_match('/^\+?\d{10,15}$/', $gsm)) {
            return new JsonResponse(['message' => 'GSM invalide'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName(trim((string)$data['firstName']));
        $user->setLastName(trim((string)$data['lastName']));
        $user->setGsm($gsm);
        $user->setPostalAddress(trim((string)$data['postalAddress']));
        $user->setRoles(['ROLE_USER']);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setPassword($passwordHasher->hashPassword($user, (string)$data['password']));

        if (method_exists($user, 'setApiToken') && empty($user->getApiToken())) {
            $user->setApiToken(bin2hex(random_bytes(32)));
        }

        $this->manager->persist($user);
        $this->manager->flush();

        try {
            $mailer->send(
                (new Email())
                    ->from('no-reply@localhost.test')
                    ->to($user->getEmail())
                    ->subject('Bienvenue chez Vite & Gourmand')
                    ->text(
                        "Bonjour ".$user->getFirstName().",\n\n".
                        "Votre compte a bien été créé.\n\n".
                        "Connectez-vous pour pouvoir commander.\n\n".
                        "Vite & Gourmand"
                    )
            );
        } catch (\Throwable $e) {}

        return new JsonResponse([
            'user' => $user->getUserIdentifier(),
            'apiToken' => $user->getApiToken(),
            'roles' => $user->getRoles()
        ], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/login',
        summary: "Connexion (json_login)",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['username','password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'admin@mail.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'Admin123!')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Connecté"),
            new OA\Response(response: 401, description: "Identifiants invalides"),
            new OA\Response(response: 403, description: "Compte désactivé"),
        ]
    )]
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return new JsonResponse(['message' => 'Missing credentials'], Response::HTTP_UNAUTHORIZED);
        }

        if (in_array('ROLE_DISABLED', $user->getRoles(), true)) {
            return new JsonResponse(['message' => 'Compte désactivé'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse([
            'user' => $user->getUserIdentifier(),
            'apiToken' => $user->getApiToken(),
            'roles' => $user->getRoles(),
        ], Response::HTTP_OK);
    }
}
