<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[OA\Tag(name: "Contact")]
#[Route('/api/contact', name: 'app_api_contact_')]
class ContactController extends AbstractController
{
    #[Route('', name: 'send', methods: ['POST'])]
    #[OA\Post(
        path: '/api/contact',
        summary: "Envoyer un message via le formulaire de contact (public)",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['title','email','description'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Demande de devis'),
                    new OA\Property(property: 'email', type: 'string', example: 'client@mail.com'),
                    new OA\Property(property: 'description', type: 'string', example: 'Bonjour, je souhaite un devis pour 20 personnes le 12/03.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Message envoyé"),
            new OA\Response(response: 400, description: "Erreur de validation"),
            new OA\Response(response: 500, description: "Erreur envoi mail"),
        ]
    )]
    public function send(Request $request, MailerInterface $mailer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $title = trim((string)($data['title'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));

        if (mb_strlen($title) < 3) {
            return $this->json(['message' => 'Titre invalide (min 3 caractères).'], Response::HTTP_BAD_REQUEST);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Email invalide.'], Response::HTTP_BAD_REQUEST);
        }
        if (mb_strlen($description) < 10) {
            return $this->json(['message' => 'Description invalide (min 10 caractères).'], Response::HTTP_BAD_REQUEST);
        }

        // Email entreprise (tu peux mettre une variable d'env si tu veux)
        $toCompany = $_ENV['CONTACT_EMAIL'] ?? 'vitegourmand@gmail.com';

        $mail = (new Email())
            ->from($_ENV['MAIL_FROM'] ?? 'no-reply@viteetgourmand.fr')
            ->replyTo($email) // ✅ le client
            ->to($toCompany)
            ->subject('[Contact] ' . $title)
            ->text("Email client: {$email}\n\nMessage:\n{$description}\n");

        try {
            $mailer->send($mail);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => 'Erreur Mailer : ' . $e->getMessage(),
                'type' => get_class($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['message' => 'Message envoyé.'], Response::HTTP_OK);
    }
}
