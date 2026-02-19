<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\WelcomeEmailService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class UserWelcomeSubscriber implements EventSubscriber
{
    public function __construct(private WelcomeEmailService $welcomeEmailService) {}

    public function getSubscribedEvents(): array
    {
        return [Events::postPersist];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        // On ne traite que les User
        if (!$entity instanceof User) {
            return;
        }

        // ✅ User vient d’être créé en base => mail de bienvenue
        $this->welcomeEmailService->send($entity);
    }
}
