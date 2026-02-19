<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use App\Entity\User;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

// ✅ MAIL
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[OA\Tag(name: "Administration")]
#[Route('/api/users', name: 'app_api_user_')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private UserRepository $repository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    // ✅ Auth via token (comme tes autres controllers)
    private function getUserFromToken(Request $request): ?User
    {
        $token = $request->headers->get('X-AUTH-TOKEN');
        if (!$token) return null;

        return $this->repository->findOneBy(['apiToken' => $token]);
    }

    private function requireAdmin(Request $request): ?User
    {
        $u = $this->getUserFromToken($request);
        if (!$u) return null;

        return in_array('ROLE_ADMIN', $u->getRoles(), true) ? $u : null;
    }

    // ================================================================
    // ✅ ADMIN : LIST EMPLOYES
    // ================================================================
    #[Route('/employees', name: 'list_employees', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users/employees',
        summary: "Lister les comptes employés (admin)",
        parameters: [
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['active','disabled']),
                description: "Optionnel : filtrer par statut"
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Liste employés"),
            new OA\Response(response: 403, description: "Accès refusé (admin requis)"),
        ]
    )]
    public function listEmployees(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);
        if (!$admin) {
            return new JsonResponse(['error' => 'Accès refusé (admin requis)'], Response::HTTP_FORBIDDEN);
        }

        $filterStatus = trim((string)$request->query->get('status', ''));

        $users = $this->repository->findBy([], ['id' => 'DESC']);

        $employees = [];
        foreach ($users as $u) {
            $roles = $u->getRoles();

            // ✅ compatible ROLE_EMPLOYEE / ROLE_EMPLOYE
            $isEmployee = in_array('ROLE_EMPLOYEE', $roles, true) || in_array('ROLE_EMPLOYE', $roles, true);
            $isDisabled = in_array('ROLE_DISABLED', $roles, true);

            if (!$isEmployee && !$isDisabled) continue;

            $status = $isDisabled ? 'disabled' : 'active';

            if ($filterStatus !== '' && $status !== $filterStatus) {
                continue;
            }

            $employees[] = [
                'id' => $u->getId(),
                'email' => $u->getEmail(),
                'roles' => $u->getRoles(),
                'status' => $status,
                'createdAt' => $u->getCreatedAt()?->format(DATE_ATOM),
                'updatedAt' => $u->getUpdatedAt()?->format(DATE_ATOM),
            ];
        }

        return new JsonResponse($employees, Response::HTTP_OK);
    }

    // ================================================================
    // ✅ ADMIN : CREATE EMPLOYEE
    // ================================================================
    #[Route('/employees', name: 'create_employee', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users/employees',
        summary: "Créer un compte employé (ADMIN uniquement)",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['email','password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'employe@mail.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'Employe123!'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Employé créé"),
            new OA\Response(response: 400, description: "Données invalides"),
            new OA\Response(response: 403, description: "Accès refusé (admin requis)"),
            new OA\Response(response: 409, description: "Email déjà utilisé"),
        ]
    )]
    public function createEmployee(Request $request, MailerInterface $mailer): JsonResponse
    {
        $admin = $this->requireAdmin($request);
        if (!$admin) {
            return new JsonResponse(['error' => 'Accès refusé (admin requis)'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $email = strtolower(trim((string)($payload['email'] ?? '')));
        $password = (string)($payload['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Email invalide'], Response::HTTP_BAD_REQUEST);
        }

        if ($password === '') {
            return new JsonResponse(['error' => 'password requis'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->repository->findOneBy(['email' => $email])) {
            return new JsonResponse(['error' => 'Email déjà utilisé'], Response::HTTP_CONFLICT);
        }

        $employee = new User();
        $employee->setEmail($email);
        $employee->setCreatedAt(new DateTimeImmutable());
        $employee->setRoles(['ROLE_EMPLOYEE']);
        $employee->setPassword($this->passwordHasher->hashPassword($employee, $password));

        // si ton User a apiToken auto : ok, sinon :
        if (method_exists($employee, 'setApiToken') && empty($employee->getApiToken())) {
            $employee->setApiToken(bin2hex(random_bytes(32)));
        }

        $this->manager->persist($employee);
        $this->manager->flush();

        // ✅ Mail (sans mot de passe)
        try {
            $mailer->send(
                (new Email())
                    ->from('no-reply@vite-et-gourmand.test')
                    ->to($employee->getEmail())
                    ->subject("Votre compte employé a été créé")
                    ->text(
                        "Bonjour,\n\n".
                        "Un compte employé a été créé pour vous sur Vite & Gourmand.\n".
                        "Identifiant : ".$employee->getEmail()."\n\n".
                        "Le mot de passe ne vous est pas communiqué par email.\n".
                        "Merci de vous rapprocher de l'administrateur.\n\n".
                        "Vite & Gourmand"
                    )
            );
        } catch (\Throwable $e) {}

        return new JsonResponse([
            'id' => $employee->getId(),
            'email' => $employee->getEmail(),
            'roles' => $employee->getRoles(),
            'status' => 'active',
        ], Response::HTTP_CREATED);
    }

    // ================================================================
    // ✅ ADMIN : DISABLE EMPLOYEE
    // ================================================================
    #[Route('/employees/{id}/disable', name: 'disable_employee', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users/employees/{id}/disable',
        summary: "Désactiver un employé (admin)",
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Employé désactivé"),
            new OA\Response(response: 400, description: "Compte non employé"),
            new OA\Response(response: 403, description: "Accès refusé"),
            new OA\Response(response: 404, description: "Utilisateur introuvable"),
        ]
    )]
    public function disableEmployee(int $id, Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);
        if (!$admin) {
            return new JsonResponse(['error' => 'Accès refusé (admin requis)'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->repository->find($id);
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $roles = $user->getRoles();
        $isEmployee = in_array('ROLE_EMPLOYEE', $roles, true) || in_array('ROLE_EMPLOYE', $roles, true);
        if (!$isEmployee) {
            return new JsonResponse(['error' => 'Compte non employé'], Response::HTTP_BAD_REQUEST);
        }

        $user->setRoles(['ROLE_DISABLED']);
        $user->setApiToken(bin2hex(random_bytes(20))); // force déconnexion token
        $user->setUpdatedAt(new DateTimeImmutable());

        $this->manager->flush();

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'status' => 'disabled',
        ], Response::HTTP_OK);
    }

    // ================================================================
    // ✅ ADMIN : ENABLE EMPLOYEE
    // ================================================================
    #[Route('/employees/{id}/enable', name: 'enable_employee', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users/employees/{id}/enable',
        summary: "Réactiver un employé (admin)",
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Employé réactivé"),
            new OA\Response(response: 400, description: "Compte pas désactivé"),
            new OA\Response(response: 403, description: "Accès refusé"),
            new OA\Response(response: 404, description: "Utilisateur introuvable"),
        ]
    )]
    public function enableEmployee(int $id, Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);
        if (!$admin) {
            return new JsonResponse(['error' => 'Accès refusé (admin requis)'], Response::HTTP_FORBIDDEN);
        }

        $user = $this->repository->find($id);
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $roles = $user->getRoles();
        $isDisabled = in_array('ROLE_DISABLED', $roles, true);
        if (!$isDisabled) {
            return new JsonResponse(['error' => 'Ce compte n’est pas désactivé'], Response::HTTP_BAD_REQUEST);
        }

        $user->setRoles(['ROLE_EMPLOYEE']);
        $user->setApiToken(bin2hex(random_bytes(20))); // nouveau token (propre)
        $user->setUpdatedAt(new DateTimeImmutable());

        $this->manager->flush();

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'status' => 'active',
        ], Response::HTTP_OK);
    }

    // ================================================================
    // ⚠️ OPTIONNEL (si tu veux garder) : création user simple
    // ================================================================
    #[Route('', name: 'new', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users',
        summary: "Créer un compte (admin only via access_control)",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['email','password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'user@mail.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'MotDePasse123!'),
                    new OA\Property(
                        property: 'roles',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['ROLE_USER']
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Compte créé"),
            new OA\Response(response: 400, description: "Email/password requis"),
            new OA\Response(response: 403, description: "Création ROLE_ADMIN interdite"),
            new OA\Response(response: 409, description: "Email déjà utilisé"),
        ]
    )]
    public function new(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload) || empty($payload['email']) || empty($payload['password'])) {
            return new JsonResponse(['error' => 'email et password requis'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->repository->findOneBy(['email' => $payload['email']])) {
            return new JsonResponse(['error' => 'Email déjà utilisé'], Response::HTTP_CONFLICT);
        }

        // ✅ sécurité : interdit de créer ROLE_ADMIN via cette route
        if (!empty($payload['roles']) && is_array($payload['roles']) && in_array('ROLE_ADMIN', $payload['roles'], true)) {
            return new JsonResponse(['error' => 'Création ROLE_ADMIN interdite'], Response::HTTP_FORBIDDEN);
        }

        $user = new User();
        $user->setEmail((string)$payload['email']);
        $user->setCreatedAt(new DateTimeImmutable());
        $user->setPassword($this->passwordHasher->hashPassword($user, (string)$payload['password']));

        if (!empty($payload['roles']) && is_array($payload['roles'])) {
            $user->setRoles($payload['roles']);
        }

        if (method_exists($user, 'setApiToken') && empty($user->getApiToken())) {
            $user->setApiToken(bin2hex(random_bytes(32)));
        }

        $this->manager->persist($user);
        $this->manager->flush();

        return new JsonResponse(['id' => $user->getId()], Response::HTTP_CREATED);
    }
}
