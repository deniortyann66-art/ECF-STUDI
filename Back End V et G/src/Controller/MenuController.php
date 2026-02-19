<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use App\Entity\Menu;
use App\Repository\MenuRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[OA\Tag(name: "Menus")]
#[Route('/api/menus', name: 'app_api_menus_')]
class MenuController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private MenuRepository $menuRepository,
        private UserRepository $userRepository,
        private SerializerInterface $serializer,
    ) {}

    /** Petit helper : auth via X-AUTH-TOKEN + option role */
    private function getUserFromToken(Request $request): ?\App\Entity\User
    {
        $token = $request->headers->get('X-AUTH-TOKEN');
        if (!$token) return null;
        return $this->userRepository->findOneBy(['apiToken' => $token]);
    }

    private function requireRole(\App\Entity\User $user, array $roles): bool
    {
        foreach ($roles as $r) {
            if (in_array($r, $user->getRoles(), true)) return true;
        }
        return false;
    }

    /** Transforme Menu en array (évite boucles de sérialisation Menu<->Dish) */
    private function menuToArray(Menu $menu, bool $withDetails = false): array
    {
        $images = [];
        foreach ($menu->getImages() as $img) {
            $images[] = [
                'id' => $img->getId(),
                'url' => $img->getUrl(),
            ];
        }

        $data = [
            'id' => $menu->getId(),
            'title' => $menu->getTitle(),
            'description' => $menu->getDescription(),
            'theme' => $menu->getTheme(),
            'diet' => $menu->getDiet(),
            'minPeople' => $menu->getMinPeople(),
            'minPrice' => $menu->getMinPrice(),
            'stock' => $menu->getStock(),
            'conditionsText' => $menu->getConditionsText(),
            'images' => $images,
        ];

        if ($withDetails) {
            $dishes = [];
            foreach ($menu->getDishes() as $dish) {
                $allergens = [];
                foreach ($dish->getAllergens() as $a) {
                    $allergens[] = [
                        'id' => $a->getId(),
                        'name' => $a->getName(),
                    ];
                }

                $dishes[] = [
                    'id' => $dish->getId(),
                    'title' => $dish->getTitle(),
                    'type' => $dish->getType(),
                    'description' => $dish->getDescription(),
                    'imageUrl' => $dish->getImageUrl(),
                    'allergens' => $allergens,
                ];
            }

            $data['dishes'] = $dishes;
        }

        return $data;
    }

    // ✅ LIST + FILTRES (dynamique côté front)
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/menus',
        summary: "Lister les menus + filtres",
        parameters: [
            new OA\Parameter(name: 'maxPrice', in: 'query', required: false, schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'minPrice', in: 'query', required: false, schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'theme', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'diet', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'minPeople', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: "Liste des menus")]
    )]
    public function list(Request $request): JsonResponse
    {
        $qb = $this->menuRepository->createQueryBuilder('m');

        // Filtres
        $maxPrice = $request->query->get('maxPrice');
        $minPrice = $request->query->get('minPrice');
        $theme = $request->query->get('theme');
        $diet = $request->query->get('diet');
        $minPeople = $request->query->get('minPeople');

        if ($maxPrice !== null && $maxPrice !== '') {
            $qb->andWhere('m.minPrice <= :maxPrice')->setParameter('maxPrice', (float)$maxPrice);
        }
        if ($minPrice !== null && $minPrice !== '') {
            $qb->andWhere('m.minPrice >= :minPrice')->setParameter('minPrice', (float)$minPrice);
        }
        if ($theme !== null && $theme !== '') {
            $qb->andWhere('m.theme = :theme')->setParameter('theme', $theme);
        }
        if ($diet !== null && $diet !== '') {
            $qb->andWhere('m.diet = :diet')->setParameter('diet', $diet);
        }
        if ($minPeople !== null && $minPeople !== '') {
            $qb->andWhere('m.minPeople >= :minPeople')->setParameter('minPeople', (int)$minPeople);
        }

        $menus = $qb->getQuery()->getResult();

        $data = [];
        foreach ($menus as $menu) {
            /** @var Menu $menu */
            $data[] = $this->menuToArray($menu, false); // list = sans détails plats/allergènes
        }

        return new JsonResponse(json_encode($data), Response::HTTP_OK, [], true);
    }

    // ✅ DETAIL MENU (avec plats + allergènes)
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(path: '/api/menus/{id}', summary: "Détail d'un menu")]
    public function show(int $id): JsonResponse
    {
        $menu = $this->menuRepository->find($id);
        if (!$menu) return new JsonResponse(null, Response::HTTP_NOT_FOUND);

        $data = $this->menuToArray($menu, true);
        return new JsonResponse(json_encode($data), Response::HTTP_OK, [], true);
    }

    // ✅ CREATE MENU (ROLE_EMPLOYEE/ROLE_ADMIN via X-AUTH-TOKEN)
    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(path: '/api/menus', summary: "Créer un menu (employé/admin)")]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUserFromToken($request);
        if (!$user) return new JsonResponse(['error' => 'Token manquant/invalide'], Response::HTTP_UNAUTHORIZED);
        if (!$this->requireRole($user, ['ROLE_EMPLOYEE', 'ROLE_ADMIN'])) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        /** @var Menu $menu */
        $menu = $this->serializer->deserialize($request->getContent(), Menu::class, 'json');

        $this->manager->persist($menu);
        $this->manager->flush();

        $data = $this->menuToArray($menu, true);
        return new JsonResponse(json_encode($data), Response::HTTP_CREATED, [], true);
    }

    // ✅ UPDATE MENU (ROLE_EMPLOYEE/ROLE_ADMIN)
    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(path: '/api/menus/{id}', summary: "Modifier un menu (employé/admin)")]
    public function edit(int $id, Request $request): JsonResponse
    {
        $user = $this->getUserFromToken($request);
        if (!$user) return new JsonResponse(['error' => 'Token manquant/invalide'], Response::HTTP_UNAUTHORIZED);
        if (!$this->requireRole($user, ['ROLE_EMPLOYEE', 'ROLE_ADMIN'])) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $menu = $this->menuRepository->find($id);
        if (!$menu) return new JsonResponse(null, Response::HTTP_NOT_FOUND);

        $this->serializer->deserialize(
            $request->getContent(),
            Menu::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $menu]
        );

        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    // ✅ DELETE MENU (ROLE_EMPLOYEE/ROLE_ADMIN)
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(path: '/api/menus/{id}', summary: "Supprimer un menu (employé/admin)")]
    public function delete(int $id, Request $request): JsonResponse
    {
        $user = $this->getUserFromToken($request);
        if (!$user) return new JsonResponse(['error' => 'Token manquant/invalide'], Response::HTTP_UNAUTHORIZED);
        if (!$this->requireRole($user, ['ROLE_EMPLOYEE', 'ROLE_ADMIN'])) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        $menu = $this->menuRepository->find($id);
        if (!$menu) return new JsonResponse(null, Response::HTTP_NOT_FOUND);

        $this->manager->remove($menu);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
