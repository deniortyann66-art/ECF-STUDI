<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class WelcomeEmailService
{
    public function __construct(private MailerInterface $mailer) {}

    public function sendWelcome(User $user): void
    {
        $email = (new Email())
            ->from('no-reply@vite-et-gourmand.fr')
            ->to($user->getEmail())
            ->subject('Bienvenue sur Vite & Gourmand 🎉')
            ->html($this->buildHtml($user));

        $this->mailer->send($email);
    }

    private function buildHtml(User $user): string
    {
        $prenom = htmlspecialchars($user->getPrenom() ?? '', ENT_QUOTES);

        return "
          <h2>Bienvenue $prenom 👋</h2>
          <p>Votre compte est bien créé.</p>
          <p>Vous pouvez maintenant vous connecter et commander vos menus.</p>
          <p>À très vite !</p>
        ";
    }
}
