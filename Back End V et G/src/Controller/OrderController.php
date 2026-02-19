<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

use App\Entity\Order;
use App\Entity\OrderStatusHistory;
use App\Repository\OrderRepository;
use App\Repository\MenuRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: "Commandes")]
#[Route('/api/orders', name: 'app_api_orders_')]
class OrderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private OrderRepository $orderRepository,
        private MenuRepository $menuRepository,
        private UserRepository $userRepository,
    ) {}

    private function getUserFromToken(Request $request): ?\App\Entity\User
    {
        $token = $request->headers->get('X-AUTH-TOKEN');
        if (!$token) return null;

        return $this->userRepository->findOneBy(['apiToken' => $token]);
    }

    private function orderToArray(Order $o): array
    {
        $menu = $o->getMenu();

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

            'menu' => $menu ? [
                'id' => $menu->getId(),
                'title' => $menu->getTitle(),
                'minPeople' => $menu->getMinPeople(),
                'minPrice' => $menu->getMinPrice(),
            ] : null,

            // ✅ pour afficher le suivi côté front
            'statusHistory' => array_map(fn($h) => [
                'status' => $h->getStatus(),
                'changedAt' => $h->getChangedAt()?->format(DATE_ATOM),
            ], $o->getStatusHistory()->toArray()),
        ];
    }

    /** Calcul prix (simple et clair pour ECF) */
    private function computePrices(Order $order): void
    {
        $menu = $order->getMenu();
        $minPeople = (int)$menu->getMinPeople();
        $minPrice = (float)$menu->getMinPrice();

        $people = (int)$order->getPeopleCount();

        // prix proportionnel (minPrice/minPeople * peopleCount)
        $pricePerPerson = $minPeople > 0 ? ($minPrice / $minPeople) : $minPrice;
        $menuPrice = $pricePerPerson * $people;

        // remise 10% si people >= minPeople + 5
        $discount = 0.0;
        if ($people >= $minPeople + 5) {
            $discount = $menuPrice * 0.10;
        }

        // livraison : si pas Bordeaux -> 5€ + 0.59€/km
        $delivery = 0.0;
        $city = mb_strtolower(trim((string)$order->getServiceCity()));
        $km = (float)($order->getKm() ?? '0.00');
        if ($city !== 'bordeaux') {
            $delivery = 5.0 + (0.59 * $km);
        }

        $total = $menuPrice - $discount + $delivery;

        // Doctrine DECIMAL = string
        $order->setMenuPrice(number_format($menuPrice, 2, '.', ''));
        $order->setDiscount(number_format($discount, 2, '.', ''));
        $order->setDeliveryPrice(number_format($delivery, 2, '.', ''));
        $order->setTotal(number_format($total, 2, '.', ''));
    }

    // ✅ LIST mes commandes (USER connecté via token)
    #[Route('', name: 'my_orders', methods: ['GET'])]
    #[OA\Get(path: '/api/orders', summary: "Lister mes commandes (token)")]
    public function myOrders(Request $request): JsonResponse
    {
        $user = $this->getUserFromToken($request);
        if (!$user) {
            return new JsonResponse(['error' => 'Token manquant/invalide'], Response::HTTP_UNAUTHORIZED);
        }

        $orders = $this->orderRepository->findBy(['user' => $user], ['id' => 'DESC']);

        $data = [];
        foreach ($orders as $o) {
            $data[] = $this->orderToArray($o);
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }

    // ✅ SHOW une commande (doit appartenir à l'utilisateur)
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(path: '/api/orders/{id}', summary: "Afficher une commande (token)")]
    public function show(int $id, Request $request): JsonResponse
    {
        $user = $this->getUserFromToken($request);
        if (!$user) {
            return new JsonResponse(['error' => 'Token manquant/invalide'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $this->orderRepository->find($id);
        if (!$order) return new JsonResponse(['error' => 'Commande introuvable'], Response::HTTP_NOT_FOUND);

        if ($order->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($this->orderToArray($order), Response::HTTP_OK);
    }

    // ✅ CREATE commande (token requis)
    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/orders',
        summary: "Créer une commande (token)",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['menuId','serviceAddress','serviceCity','serviceDate','serviceTime','peopleCount'],
                properties: [
                    new OA\Property(property: 'menuId', type: 'integer', example: 1),
                    new OA\Property(property: 'serviceAddress', type: 'string', example: '10 rue Victor Hugo'),
                    new OA\Property(property: 'serviceCity', type: 'string', example: 'Bordeaux'),
                    new OA\Property(property: 'serviceDate', type: 'string', example: '2026-03-12'),
                    new OA\Property(property: 'serviceTime', type: 'string', example: '18:30'),
                    new OA\Property(property: 'peopleCount', type: 'integer', example: 10),
                    new OA\Property(property: 'km', type: 'number', example: 15.5),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Commande créée"),
            new OA\Response(response: 400, description: "Erreur validation"),
            new OA\Response(response: 401, description: "Token manquant/invalide"),
            new OA\Response(response: 404, description: "Menu introuvable"),
            new OA\Response(response: 409, description: "Stock indisponible"),
        ]
    )]
    public function create(Request $request, MailerInterface $mailer): JsonResponse
    {
        $user = $this->getUserFromToken($request);
        if (!$user) {
            return new JsonResponse(['error' => 'Token manquant/invalide'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $menuId = (int)($payload['menuId'] ?? 0);
        $menu = $this->menuRepository->find($menuId);
        if (!$menu) return new JsonResponse(['error' => 'Menu introuvable'], Response::HTTP_NOT_FOUND);

        // stock
        if ((int)$menu->getStock() <= 0) {
            return new JsonResponse(['error' => 'Stock indisponible'], Response::HTTP_CONFLICT);
        }

        $peopleCount = (int)($payload['peopleCount'] ?? 0);
        if ($peopleCount < (int)$menu->getMinPeople()) {
            return new JsonResponse(['error' => 'peopleCount doit être >= minPeople du menu'], Response::HTTP_BAD_REQUEST);
        }

        // parsing dates
        $serviceDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string)($payload['serviceDate'] ?? ''));
        $serviceTime = \DateTimeImmutable::createFromFormat('H:i', (string)($payload['serviceTime'] ?? ''));

        if (!$serviceDate || !$serviceTime) {
            return new JsonResponse(['error' => 'serviceDate (Y-m-d) et serviceTime (H:i) obligatoires'], Response::HTTP_BAD_REQUEST);
        }

        $order = new Order();
        $order->setUser($user);
        $order->setMenu($menu);

        $order->setServiceAddress((string)($payload['serviceAddress'] ?? ''));
        $order->setServiceCity((string)($payload['serviceCity'] ?? ''));
        $order->setServiceDate($serviceDate);
        $order->setServiceTime($serviceTime);

        $order->setPeopleCount($peopleCount);
        $order->setKm(isset($payload['km']) ? (string)$payload['km'] : '0.00');

        // prix
        $this->computePrices($order);

        // ✅ décrémenter stock
        $menu->setStock(((int)$menu->getStock()) - 1);

        // ✅ Historique initial (recu)
        $h = new OrderStatusHistory();
        $h->setStatus(Order::STATUS_RECU);
        $order->addStatusHistory($h);
        $this->manager->persist($h);

        $this->manager->persist($order);
        $this->manager->flush();

        // ✅ Mail de confirmation (ne bloque pas la commande si échec mail)
        try {
            $menuTitle = $menu->getTitle();
            $serviceDateStr = $order->getServiceDate()?->format('d/m/Y') ?? '';
            $serviceTimeStr = $order->getServiceTime()?->format('H:i') ?? '';

            $text = "Bonjour ".$user->getFirstName().",\n\n"
                ."✅ Votre commande a bien été enregistrée.\n"
                ."Commande #".$order->getId()."\n\n"
                ."Menu : ".$menuTitle."\n"
                ."Date : ".$serviceDateStr." à ".$serviceTimeStr."\n"
                ."Adresse : ".$order->getServiceAddress().", ".$order->getServiceCity()."\n"
                ."Personnes : ".$order->getPeopleCount()."\n\n"
                ."Détail prix :\n"
                ."- Prix menu : ".$order->getMenuPrice()." €\n"
                ."- Remise : -".$order->getDiscount()." €\n"
                ."- Livraison : ".$order->getDeliveryPrice()." €\n"
                ."TOTAL : ".$order->getTotal()." €\n\n"
                ."Vite & Gourmand";

            $html = "
                <h2>Confirmation de commande ✅</h2>
                <p>Bonjour <strong>".htmlspecialchars((string)$user->getFirstName())."</strong>,</p>
                <p>Votre commande <strong>#".$order->getId()."</strong> a bien été enregistrée.</p>

                <h3>Détails</h3>
                <ul>
                    <li><strong>Menu :</strong> ".htmlspecialchars((string)$menuTitle)."</li>
                    <li><strong>Date :</strong> ".htmlspecialchars((string)$serviceDateStr)." à ".htmlspecialchars((string)$serviceTimeStr)."</li>
                    <li><strong>Adresse :</strong> ".htmlspecialchars((string)$order->getServiceAddress()).", ".htmlspecialchars((string)$order->getServiceCity())."</li>
                    <li><strong>Personnes :</strong> ".(int)$order->getPeopleCount()."</li>
                </ul>

                <h3>Détail du prix</h3>
                <table border='1' cellpadding='8' cellspacing='0'>
                    <tr><td>Prix menu</td><td>".$order->getMenuPrice()." €</td></tr>
                    <tr><td>Remise</td><td>-".$order->getDiscount()." €</td></tr>
                    <tr><td>Livraison</td><td>".$order->getDeliveryPrice()." €</td></tr>
                    <tr><td><strong>Total</strong></td><td><strong>".$order->getTotal()." €</strong></td></tr>
                </table>

                <p>Merci pour votre confiance,<br><strong>Vite & Gourmand</strong></p>
            ";

            $mailer->send(
                (new Email())
                    ->from('no-reply@vite-et-gourmand.test')
                    ->to($user->getEmail())
                    ->subject('Confirmation de votre commande #'.$order->getId())
                    ->text($text)
                    ->html($html)
            );
        } catch (\Throwable $e) {
            // ne pas bloquer si mail échoue
        }

        return new JsonResponse($this->orderToArray($order), Response::HTTP_CREATED);
    }

    // ✅ UPDATE commande (modif possible tant que pas "accepte")
    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(path: '/api/orders/{id}', summary: "Modifier une commande (token)")]
    public function edit(int $id, Request $request): JsonResponse
    {
        $user = $this->getUserFromToken($request);
        if (!$user) {
            return new JsonResponse(['error' => 'Token manquant/invalide'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $this->orderRepository->find($id);
        if (!$order) return new JsonResponse(['error' => 'Commande introuvable'], Response::HTTP_NOT_FOUND);

        if ($order->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        // Si déjà accepté => pas modifiable par user
        if ($order->getStatus() !== Order::STATUS_RECU) {
            return new JsonResponse(['error' => 'Commande non modifiable (déjà traitée)'], Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        // menu non modifiable : on ignore menuId si présent
        if (isset($payload['serviceAddress'])) $order->setServiceAddress((string)$payload['serviceAddress']);
        if (isset($payload['serviceCity'])) $order->setServiceCity((string)$payload['serviceCity']);
        if (isset($payload['km'])) $order->setKm((string)$payload['km']);

        if (isset($payload['serviceDate'])) {
            $d = \DateTimeImmutable::createFromFormat('Y-m-d', (string)$payload['serviceDate']);
            if (!$d) return new JsonResponse(['error' => 'serviceDate invalide (Y-m-d)'], Response::HTTP_BAD_REQUEST);
            $order->setServiceDate($d);
        }

        if (isset($payload['serviceTime'])) {
            $t = \DateTimeImmutable::createFromFormat('H:i', (string)$payload['serviceTime']);
            if (!$t) return new JsonResponse(['error' => 'serviceTime invalide (H:i)'], Response::HTTP_BAD_REQUEST);
            $order->setServiceTime($t);
        }

        if (isset($payload['peopleCount'])) {
            $people = (int)$payload['peopleCount'];
            $minPeople = (int)$order->getMenu()->getMinPeople();
            if ($people < $minPeople) {
                return new JsonResponse(['error' => 'peopleCount doit être >= minPeople du menu'], Response::HTTP_BAD_REQUEST);
            }
            $order->setPeopleCount($people);
        }

        // recalcul
        $this->computePrices($order);

        $this->manager->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    // ✅ CANCEL commande (user) tant que pas accepté + historique
    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    #[OA\Post(path: '/api/orders/{id}/cancel', summary: "Annuler une commande (token)")]
    public function cancel(int $id, Request $request): JsonResponse
    {
        $user = $this->getUserFromToken($request);
        if (!$user) {
            return new JsonResponse(['error' => 'Token manquant/invalide'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $this->orderRepository->find($id);
        if (!$order) return new JsonResponse(['error' => 'Commande introuvable'], Response::HTTP_NOT_FOUND);

        if ($order->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        if ($order->getStatus() !== Order::STATUS_RECU) {
            return new JsonResponse(['error' => 'Annulation impossible (déjà acceptée)'], Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true);
        $reason = is_array($payload) ? trim((string)($payload['cancelReason'] ?? '')) : '';
        $order->setCancelReason($reason !== '' ? $reason : 'Annulation par le client');

        // ✅ statut + historique
        $order->setStatus(Order::STATUS_ANNULEE);

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

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
