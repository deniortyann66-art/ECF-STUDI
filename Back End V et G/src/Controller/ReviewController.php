<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use App\Entity\Order;
use App\Entity\Review;
use App\Repository\OrderRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: "Avis")]
#[Route('/api/reviews')]
class ReviewController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReviewRepository $reviewRepository,
        private OrderRepository $orderRepository,
        private UserRepository $userRepository,
    ) {}

    /**
     * ✅ récupère l'user soit via auth Symfony, soit via X-AUTH-TOKEN
     */
    private function getAuthUser(Request $request): ?\App\Entity\User
    {
        $u = $this->getUser();
        if ($u instanceof \App\Entity\User) {
            return $u;
        }

        $token = $request->headers->get('X-AUTH-TOKEN');
        if (!$token) return null;

        return $this->userRepository->findOneBy(['apiToken' => $token]);
    }

    private function isEmployeeOrAdmin(?\App\Entity\User $user): bool
    {
        if (!$user) return false;

        $roles = $user->getRoles();

        // ✅ on couvre plusieurs variantes possibles
        $employeeRoles = ['ROLE_EMPLOYEE', 'ROLE_EMPLOYE', 'ROLE_STAFF'];
        $isEmployee = (bool) array_intersect($employeeRoles, $roles);
        $isAdmin = in_array('ROLE_ADMIN', $roles, true);

        return $isEmployee || $isAdmin;
    }

    private function toArray(Review $r): array
    {
        $u = $r->getUserRef();

        return [
            'id' => $r->getId(),
            'rating' => $r->getRating(),
            'comment' => $r->getComment(),
            'isValidated' => (bool)$r->isValidated(),
            'createdAt' => $r->getCreatedAt()?->format(DATE_ATOM),
            'validatedAt' => $r->getValidatedAt()?->format(DATE_ATOM),
            'user' => $u ? [
                'id' => $u->getId(),
                'firstName' => method_exists($u, 'getFirstName') ? $u->getFirstName() : null,
                'email' => method_exists($u, 'getEmail') ? $u->getEmail() : null,
            ] : null,
        ];
    }

    #[Route('/validated', methods: ['GET'])]
    public function validated(Request $request): JsonResponse
    {
        $limit = (int)($request->query->get('limit', 9));
        if ($limit <= 0 || $limit > 50) $limit = 9;

        $reviews = $this->reviewRepository->findBy(
            ['isValidated' => true],
            ['createdAt' => 'DESC'],
            $limit
        );

        return new JsonResponse(array_map(fn($r) => $this->toArray($r), $reviews), Response::HTTP_OK);
    }

    #[Route('/orders/{orderId}', methods: ['POST'])]
    public function createForOrder(int $orderId, Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        if (!$user) return new JsonResponse(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);

        $order = $this->orderRepository->find($orderId);
        if (!$order) return new JsonResponse(['message' => 'Commande introuvable'], Response::HTTP_NOT_FOUND);

        if ($order->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse(['message' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        if ($order->getStatus() !== Order::STATUS_TERMINEE) {
            return new JsonResponse(['message' => 'Avis possible uniquement quand la commande est terminée'], Response::HTTP_CONFLICT);
        }

        if ($order->getReview()) {
            return new JsonResponse(['message' => 'Avis déjà envoyé pour cette commande'], Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) return new JsonResponse(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);

        $rating = (int)($payload['rating'] ?? 0);
        $comment = trim((string)($payload['comment'] ?? ''));

        if ($rating < 1 || $rating > 5) {
            return new JsonResponse(['message' => 'rating doit être entre 1 et 5'], Response::HTTP_BAD_REQUEST);
        }
        if ($comment === '') {
            return new JsonResponse(['message' => 'comment obligatoire'], Response::HTTP_BAD_REQUEST);
        }

        $review = new Review();
        $review->setOrderRef($order);
        $review->setUserRef($user);
        $review->setRating($rating);
        $review->setComment($comment);
        $review->setIsValidated(false);

        $order->setReview($review);

        $this->em->persist($review);
        $this->em->flush();

        return new JsonResponse($this->toArray($review), Response::HTTP_CREATED);
    }

    #[Route('/pending', methods: ['GET'])]
    public function pending(Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        if (!$this->isEmployeeOrAdmin($user)) {
            return new JsonResponse([
                'message' => 'Accès refusé (employé/admin requis)',
                'debugRoles' => $user?->getRoles() ?? [],
            ], Response::HTTP_FORBIDDEN);
        }

        $reviews = $this->reviewRepository->findBy(['isValidated' => false], ['createdAt' => 'DESC']);
        return new JsonResponse(array_map(fn($r) => $this->toArray($r), $reviews), Response::HTTP_OK);
    }

    #[Route('/{id}/validate', methods: ['POST'])]
    public function validateReview(int $id, Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        if (!$this->isEmployeeOrAdmin($user)) {
            return new JsonResponse([
                'message' => 'Accès refusé (employé/admin requis)',
                'debugRoles' => $user?->getRoles() ?? [],
            ], Response::HTTP_FORBIDDEN);
        }

        $review = $this->reviewRepository->find($id);
        if (!$review) return new JsonResponse(['message' => 'Avis introuvable'], Response::HTTP_NOT_FOUND);

        $review->setIsValidated(true);
        $review->setValidatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return new JsonResponse($this->toArray($review), Response::HTTP_OK);
    }

    #[Route('/{id}/refuse', methods: ['POST'])]
    public function refuseReview(int $id, Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        if (!$this->isEmployeeOrAdmin($user)) {
            return new JsonResponse([
                'message' => 'Accès refusé (employé/admin requis)',
                'debugRoles' => $user?->getRoles() ?? [],
            ], Response::HTTP_FORBIDDEN);
        }

        $review = $this->reviewRepository->find($id);
        if (!$review) return new JsonResponse(['message' => 'Avis introuvable'], Response::HTTP_NOT_FOUND);

        $this->em->remove($review);
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
