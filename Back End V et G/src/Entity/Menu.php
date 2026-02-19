<?php

namespace App\Entity;

use App\Repository\MenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuRepository::class)]
class Menu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $theme = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $diet = null;

    #[ORM\Column]
    private ?int $minPeople = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $minPrice = null;

    #[ORM\Column]
    private ?int $stock = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $conditionsText = null;

    /**
     * @var Collection<int, MenuImage>
     */
    #[ORM\OneToMany(targetEntity: MenuImage::class, mappedBy: 'menu', orphanRemoval: true)]
    private Collection $images;

    /**
     * @var Collection<int, Dish>
     */
    #[ORM\ManyToMany(targetEntity: Dish::class, inversedBy: 'menus')]
    private Collection $dishes;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'menu')]
    private Collection $orders;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->dishes = new ArrayCollection();
        $this->orders = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getDiet(): ?string
    {
        return $this->diet;
    }

    public function setDiet(?string $diet): static
    {
        $this->diet = $diet;

        return $this;
    }

    public function getMinPeople(): ?int
    {
        return $this->minPeople;
    }

    public function setMinPeople(int $minPeople): static
    {
        $this->minPeople = $minPeople;

        return $this;
    }

    public function getMinPrice(): ?string
    {
        return $this->minPrice;
    }

    public function setMinPrice(?string $minPrice): static
    {
        $this->minPrice = $minPrice;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getConditionsText(): ?string
    {
        return $this->conditionsText;
    }

    public function setConditionsText(string $conditionsText): static
    {
        $this->conditionsText = $conditionsText;

        return $this;
    }

    /**
     * @return Collection<int, MenuImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(MenuImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setMenu($this);
        }

        return $this;
    }

    public function removeImage(MenuImage $image): static
{
    $this->images->removeElement($image);
    return $this;
}


    /**
     * @return Collection<int, Dish>
     */
    public function getDishes(): Collection
    {
        return $this->dishes;
    }

    public function addDish(Dish $dish): static
{
    if (!$this->dishes->contains($dish)) {
        $this->dishes->add($dish);
        $dish->addMenu($this);
    }

    return $this;
}


    public function removeDish(Dish $dish): static
{
    if ($this->dishes->removeElement($dish)) {
        $dish->removeMenu($this);
    }

    return $this;
}


    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setMenu($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
{
    $this->orders->removeElement($order);
    return $this;
}

}
