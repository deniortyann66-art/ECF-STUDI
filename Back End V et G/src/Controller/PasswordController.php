<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use App\Entity\PasswordResetToken;
use App\Repository\UserRepository;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, JsonResponse, Response};
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: "Password")]
#[Route('/api')]
class PasswordController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager
    ) {}

    // ================================================================
    // ✅ FORGOT PASSWORD
    // ================================================================
    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    #[OA\Post(
        path: '/api/forgot-password',
        summary: "Demander un lien de réinitialisation (public)",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'user@mail.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Lien envoyé"),
            new OA\Response(response: 400, description: "Email invalide / JSON invalide"),
            new OA\Response(response: 404, description: "Email non reconnu"),
        ]
    )]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepo,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $email = strtolower(trim((string)($data['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['message' => 'Email invalide'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepo->findOneBy(['email' => $email]);
        if (!$user) {
            return new JsonResponse(['message' => 'Email non reconnu sur notre site'], Response::HTTP_NOT_FOUND);
        }

        // Génère un token sécurisé
        $tokenValue = bin2hex(random_bytes(32)); // 64 chars

        $token = new PasswordResetToken();
        $token->setUser($user);
        $token->setToken($tokenValue);
        $token->setExpiresAt((new \DateTimeImmutable())->modify('+1 hour'));
        $token->setUsedAt(null);

        $this->manager->persist($token);
        $this->manager->flush();

        // ✅ URL front configurable
        // Ex: RESET_FRONT_URL=http://localhost:5173/reset-password
        $frontReset = $_ENV['RESET_FRONT_URL'] ?? 'http://localhost:5173/reset-password';
        $resetLink = rtrim($frontReset, '/') . '?token=' . $tokenValue;

        $mail = (new TemplatedEmail())
            ->from(new Address($_ENV['MAIL_FROM'] ?? 'no-reply@viteetgourmand.fr', 'Vite & Gourmand'))
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe')
            ->htmlTemplate('emails/reset_password.html.twig')
            ->context([
                'resetLink' => $resetLink,
                'validity' => '1 heure',
            ]);

        try {
            $mailer->send($mail);
        } catch (\Throwable $e) {
            // Optionnel : ne pas leak l'erreur en prod
            return new JsonResponse(['message' => 'Erreur envoi email'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['message' => 'Lien de réinitialisation envoyé.'], Response::HTTP_OK);
    }

    // ================================================================
    // ✅ RESET PASSWORD
    // ================================================================
    #[Route('/reset-password', name: 'reset_password', methods: ['POST'])]
    #[OA\Post(
        path: '/api/reset-password',
        summary: "Réinitialiser le mot de passe (public via token)",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['token','password'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', example: 'a1b2c3...'),
                    new OA\Property(property: 'password', type: 'string', example: 'NouveauMotDePasse123!'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Mot de passe mis à jour"),
            new OA\Response(response: 400, description: "Token/password manquants ou token invalide"),
            new OA\Response(response: 422, description: "Mot de passe trop faible"),
        ]
    )]
    public function resetPassword(
        Request $request,
        PasswordResetTokenRepository $tokenRepo,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $tokenValue = trim((string)($data['token'] ?? ''));
        $newPassword = (string)($data['password'] ?? '');

        if ($tokenValue === '' || $newPassword === '') {
            return new JsonResponse(['message' => 'Token et mot de passe requis'], Response::HTTP_BAD_REQUEST);
        }

        // Mot de passe fort (mêmes règles que l’inscription)
        $passwordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{10,}$/';
        if (!preg_match($passwordRegex, $newPassword)) {
            return new JsonResponse(
                ['message' => 'Mot de passe trop faible (10 caractères min + maj + min + chiffre + spécial)'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $token = $tokenRepo->findOneBy(['token' => $tokenValue]);

        // Vérifs : token existe + pas expiré + pas utilisé
        if (!$token || !$token->isValid()) {
            return new JsonResponse(['message' => 'Token invalide, expiré ou déjà utilisé'], Response::HTTP_BAD_REQUEST);
        }

        $user = $token->getUser();
        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));

        $token->setUsedAt(new \DateTimeImmutable());

        $this->manager->flush();

        return new JsonResponse(['message' => 'Mot de passe mis à jour'], Response::HTTP_OK);
    }
}
