<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use App\Entity\Order;
use App\Entity\OrderStatusHistory;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;

// ✅ MAIL
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[OA\Tag(name: "Employé")]
#[Route('/api/employee', name: 'app_api_employee_')]
class EmployeeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private OrderRepository $orderRepository,
        private UserRepository $userRepository,
    ) {}

    private function getUserFromToken(Request $request): ?\App\Entity\User
    {
        $token = $request->headers->get('X-AUTH-TOKEN');
        if (!$token) return null;

        return $this->userRepository->findOneBy(['apiToken' => $token]);
    }

    private function requireEmployeeOrAdmin(Request $request): ?\App\Entity\User
    {
        $user = $this->getUserFromToken($request);
        if (!$user) return null;

        $roles = $user->getRoles();

        // ✅ compatible front + back (ROLE_EMPLOYEE / ROLE_EMPLOYE)
        $isEmployee = in_array('ROLE_EMPLOYEE', $roles, true) || in_array('ROLE_EMPLOYE', $roles, true);
        $isAdmin    = in_array('ROLE_ADMIN', $roles, true);

        return ($isEmployee || $isAdmin) ? $user : null;
    }

    private function orderToArray(Order $o): array
    {
        $u = $o->getUser();
        $m = $o->getMenu();

        return [
            'id' => $o->getId(),
            'status' => $o->getStatus(),
            'cancelReason' => $o->getCancelReason(),
            'createdAt' => $o->getCreatedAt()?->format(DATE_ATOM),

            'serviceAddress' => $o->getServiceAddress(),
            'serviceCity' => $o->getServiceCity(),
            'serviceDate' => $o->getServiceDate()?->format('Y-m-d'),
            'serviceTime' => $o->getServiceTime()?->format('H:i'),
            'peopleCount' => $o->getPeopleCount(),

            'km' => $o->getKm(),
            'menuPrice' => $o->getMenuPrice(),
            'deliveryPrice' => $o->getDeliveryPrice(),
            'discount' => $o->getDiscount(),
            'total' => $o->getTotal(),

            'user' => $u ? [
                'id' => $u->getId(),
                'email' => $u->getEmail(),
                'firstName' => method_exists($u, 'getFirstName') ? $u->getFirstName() : null,
                'lastName' => method_exists($u, 'getLastName') ? $u->getLastName() : null,
                'gsm' => method_exists($u, 'getGsm') ? $u->getGsm() : null,
            ] : null,

            'menu' => $m ? [
                'id' => $m->getId(),
                'title' => $m->getTitle(),
            ] : null,

            'statusHistory' => array_map(fn($h) => [
                'status' => $h->getStatus(),
                'changedAt' => $h->getChangedAt()?->format(DATE_ATOM),
            ], $o->getStatusHistory()->toArray()),
        ];
    }

    // ✅ LIST commandes (filtre par statut et/ou email client)
    #[Route('/orders', name: 'orders_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/employee/orders',
        summary: "Lister commandes (employé/admin) avec filtres",
        parameters: [
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string'),
                description: "Filtrer par statut (ex: recu, accepte, preparation, livraison, livre, terminee, annulee)"
            ),
            new OA\Parameter(
                name: 'email',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string'),
                description: "Filtrer par email client"
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: "Liste des commandes"),
            new OA\Response(response: 403, description: "Accès refusé"),
        ]
    )]
    public function ordersList(Request $request): JsonResponse
    {
        $auth = $this->requireEmployeeOrAdmin($request);
        if (!$auth) return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);

        $status = (string)$request->query->get('status', '');
        $email  = (string)$request->query->get('email', '');

        $qb = $this->orderRepository->createQueryBuilder('o')
            ->leftJoin('o.user', 'u')->addSelect('u')
            ->leftJoin('o.menu', 'm')->addSelect('m')
            ->orderBy('o.id', 'DESC');

        if ($status !== '') {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        if ($email !== '') {
            $qb->andWhere('u.email = :email')->setParameter('email', $email);
        }

        $orders = $qb->getQuery()->getResult();

        $data = [];
        foreach ($orders as $o) {
            $data[] = $this->orderToArray($o);
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }

    // ✅ UPDATE statut commande (employé/admin) + historique + mail si TERMINÉE
    #[Route('/orders/{id}/status', name: 'orders_status', methods: ['POST'])]
    #[OA\Post(
        path: '/api/employee/orders/{id}/status',
        summary: "Changer le statut d'une commande (employé/admin)",
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['status'],
                properties: [
                    new OA\Property(
                        property: 'status',
                        type: 'string',
                        example: 'accepte',
                        enum: ['recu','accepte','preparation','livraison','livre','terminee','annulee']
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Statut modifié"),
            new OA\Response(response: 400, description: "JSON/statut invalide"),
            new OA\Response(response: 403, description: "Accès refusé"),
            new OA\Response(response: 404, description: "Commande introuvable"),
        ]
    )]
    public function updateStatus(int $id, Request $request, MailerInterface $mailer): JsonResponse
    {
        $auth = $this->requireEmployeeOrAdmin($request);
        if (!$auth) return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);

        $order = $this->orderRepository->find($id);
        if (!$order) return new JsonResponse(['error' => 'Commande introuvable'], Response::HTTP_NOT_FOUND);

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) return new JsonResponse(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);

        $newStatus = trim((string)($payload['status'] ?? ''));
        if ($newStatus === '') return new JsonResponse(['error' => 'status obligatoire'], Response::HTTP_BAD_REQUEST);

        if (!in_array($newStatus, Order::getAvailableStatuses(), true)) {
            return new JsonResponse(['error' => 'Statut invalide'], Response::HTTP_BAD_REQUEST);
        }

        $order->setStatus($newStatus);

        // ✅ Historique obligatoire ECF
        $h = new OrderStatusHistory();
        $h->setStatus($newStatus);
        $order->addStatusHistory($h);
        $this->manager->persist($h);

        $this->manager->flush();

        // ✅ Mail automatique quand la commande est "terminée" -> inviter à laisser un avis
        if ($newStatus === Order::STATUS_TERMINEE) {
            $user = $order->getUser();
            if ($user && $user->getEmail()) {
                try {
                    $menuTitle = $order->getMenu()?->getTitle() ?? 'Votre menu';

                    // ⚠️ adapte si besoin, sinon mets FRONT_URL dans .env
                    $frontUrl = $_ENV['FRONT_URL'] ?? 'http://localhost:5173';
                    $linkAvis = rtrim($frontUrl, '/') . '/espace-client/commandes';

                    $text = "Bonjour ".$user->getFirstName().",\n\n"
                        ."✅ Votre commande #".$order->getId()." est maintenant terminée.\n"
                        ."Menu : ".$menuTitle."\n\n"
                        ."Vous pouvez vous connecter pour laisser un avis (note 1 à 5 + commentaire) :\n"
                        .$linkAvis."\n\n"
                        ."Merci,\nVite & Gourmand";

                    $html = "
                        <h2>Commande terminée ✅</h2>
                        <p>Bonjour <strong>".htmlspecialchars((string)$user->getFirstName())."</strong>,</p>
                        <p>Votre commande <strong>#".$order->getId()."</strong> est maintenant <strong>terminée</strong>.</p>
                        <p><strong>Menu :</strong> ".htmlspecialchars((string)$menuTitle)."</p>
                        <p>Vous pouvez laisser un avis (note 1 à 5 + commentaire) depuis votre espace :</p>
                        <p><a href='".htmlspecialchars((string)$linkAvis)."'>Accéder à mon espace commandes</a></p>
                        <p>Merci pour votre confiance,<br><strong>Vite & Gourmand</strong></p>
                    ";

                    $mailer->send(
                        (new Email())
                            ->from('no-reply@vite-et-gourmand.test')
                            ->to($user->getEmail())
                            ->subject('Votre commande #'.$order->getId().' est terminée — donnez votre avis')
                            ->text($text)
                            ->html($html)
                    );
                } catch (\Throwable $e) {
                    // ne pas bloquer le changement de statut si le mail échoue
                }
            }
        }

        return new JsonResponse($this->orderToArray($order), Response::HTTP_OK);
    }

    // ✅ ANNULER commande côté employé avec motif obligatoire + historique
    #[Route('/orders/{id}/cancel', name: 'orders_cancel', methods: ['POST'])]
    #[OA\Post(
        path: '/api/employee/orders/{id}/cancel',
        summary: "Annuler une commande (employé/admin) avec motif",
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['cancelReason'],
                properties: [
                    new OA\Property(
                        property: 'cancelReason',
                        type: 'string',
                        example: 'Téléphone : client absent à l’adresse — annulation'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Commande annulée"),
            new OA\Response(response: 400, description: "cancelReason obligatoire / JSON invalide"),
            new OA\Response(response: 403, description: "Accès refusé"),
            new OA\Response(response: 404, description: "Commande introuvable"),
        ]
    )]
    public function cancelOrder(int $id, Request $request): JsonResponse
    {
        $auth = $this->requireEmployeeOrAdmin($request);
        if (!$auth) return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);

        $order = $this->orderRepository->find($id);
        if (!$order) return new JsonResponse(['error' => 'Commande introuvable'], Response::HTTP_NOT_FOUND);

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) return new JsonResponse(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);

        $reason = trim((string)($payload['cancelReason'] ?? ''));
        if ($reason === '') {
            return new JsonResponse(
                ['error' => 'cancelReason obligatoire (mode de contact + motif)'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // ✅ statut + raison
        $order->setStatus(Order::STATUS_ANNULEE);
        $order->setCancelReason($reason);

        // ✅ Historique aussi pour l'annulation
        $h = new OrderStatusHistory();
        $h->setStatus(Order::STATUS_ANNULEE);
        $order->addStatusHistory($h);
        $this->manager->persist($h);

        // ✅ remettre stock +1
        $menu = $order->getMenu();
        if ($menu) {
            $menu->setStock(((int)$menu->getStock()) + 1);
        }

        $this->manager->flush();

        return new JsonResponse($this->orderToArray($order), Response::HTTP_OK);
    }
}
