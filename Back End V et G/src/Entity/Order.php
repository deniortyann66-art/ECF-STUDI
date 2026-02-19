<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
class Order
{
    public const STATUS_RECU = 'recu';
    public const STATUS_ACCEPTE = 'accepte';
    public const STATUS_PREPARATION = 'preparation';
    public const STATUS_LIVRAISON = 'livraison';
    public const STATUS_LIVRE = 'livre';
    public const STATUS_ATTENTE_RETOUR = 'attente_retour_materiel';
    public const STATUS_TERMINEE = 'terminee';
    public const STATUS_ANNULEE = 'annulee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Menu $menu = null;

    #[ORM\Column(length: 255)]
    private ?string $serviceAddress = null;

    #[ORM\Column(length: 130)]
    private ?string $serviceCity = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $serviceDate = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $serviceTime = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $km = null;

    #[ORM\Column]
    private ?int $peopleCount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $menuPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $deliveryPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $discount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $total = null;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cancelReason = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToOne(mappedBy: 'orderRef', cascade: ['persist', 'remove'])]
    private ?Review $review = null;

    /**
     * @var Collection<int, OrderStatusHistory>
     */
    #[ORM\OneToMany(
        targetEntity: OrderStatusHistory::class,
        mappedBy: 'orderRef',
        orphanRemoval: true
    )]
    private Collection $statusHistory;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_RECU;
        $this->discount = '0.00';
        $this->statusHistory = new ArrayCollection();
    }

    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_RECU,
            self::STATUS_ACCEPTE,
            self::STATUS_PREPARATION,
            self::STATUS_LIVRAISON,
            self::STATUS_LIVRE,
            self::STATUS_ATTENTE_RETOUR,
            self::STATUS_TERMINEE,
            self::STATUS_ANNULEE, // ✅ au lieu de 'annulee'
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): static
    {
        $this->menu = $menu;
        return $this;
    }

    public function getServiceAddress(): ?string
    {
        return $this->serviceAddress;
    }

    public function setServiceAddress(string $serviceAddress): static
    {
        $this->serviceAddress = $serviceAddress;
        return $this;
    }

    public function getServiceCity(): ?string
    {
        return $this->serviceCity;
    }

    public function setServiceCity(string $serviceCity): static
    {
        $this->serviceCity = $serviceCity;
        return $this;
    }

    public function getServiceDate(): ?\DateTimeImmutable
    {
        return $this->serviceDate;
    }

    public function setServiceDate(\DateTimeImmutable $serviceDate): static
    {
        $this->serviceDate = $serviceDate;
        return $this;
    }

    public function getServiceTime(): ?\DateTimeImmutable
    {
        return $this->serviceTime;
    }

    public function setServiceTime(\DateTimeImmutable $serviceTime): static
    {
        $this->serviceTime = $serviceTime;
        return $this;
    }

    public function getKm(): ?string
    {
        return $this->km;
    }

    public function setKm(?string $km): static
    {
        $this->km = $km;
        return $this;
    }

    public function getPeopleCount(): ?int
    {
        return $this->peopleCount;
    }

    public function setPeopleCount(int $peopleCount): static
    {
        $this->peopleCount = $peopleCount;
        return $this;
    }

    public function getMenuPrice(): ?string
    {
        return $this->menuPrice;
    }

    public function setMenuPrice(string $menuPrice): static
    {
        $this->menuPrice = $menuPrice;
        return $this;
    }

    public function getDeliveryPrice(): ?string
    {
        return $this->deliveryPrice;
    }

    public function setDeliveryPrice(string $deliveryPrice): static
    {
        $this->deliveryPrice = $deliveryPrice;
        return $this;
    }

    public function getDiscount(): ?string
    {
        return $this->discount;
    }

    public function setDiscount(string $discount): static
    {
        $this->discount = $discount;
        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function setCancelReason(?string $cancelReason): static
    {
        $this->cancelReason = $cancelReason;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(Review $review): static
    {
        if ($review->getOrderRef() !== $this) {
            $review->setOrderRef($this);
        }

        $this->review = $review;
        return $this;
    }

    /**
     * @return Collection<int, OrderStatusHistory>
     */
    public function getStatusHistory(): Collection
    {
        return $this->statusHistory;
    }

    public function addStatusHistory(OrderStatusHistory $statusHistory): static
    {
        if (!$this->statusHistory->contains($statusHistory)) {
            $this->statusHistory->add($statusHistory);
            $statusHistory->setOrderRef($this);
        }

        return $this;
    }

    public function removeStatusHistory(OrderStatusHistory $statusHistory): static
    {
        if ($this->statusHistory->removeElement($statusHistory)) {
            if ($statusHistory->getOrderRef() === $this) {
                $statusHistory->setOrderRef(null);
            }
        }

        return $this;
    }
}
