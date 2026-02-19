<?php

namespace App\Entity;



use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;




#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255)]
    private ?string $apiToken = null;

    public function __construct()
{
    $this->createdAt = new \DateTimeImmutable();
    $this->apiToken = bin2hex(random_bytes(20));
    $this->orders = new ArrayCollection();
    $this->passwordResetTokens = new ArrayCollection();
    $this->reviews = new ArrayCollection();
}



    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastName = null;

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getApiToken(): ?string
    {
        return $this->apiToken;
    }

    public function setApiToken(string $apiToken): static
    {
        $this->apiToken = $apiToken;

        return $this;
    }
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $gsm = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $postalAddress = null;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'user')]
    private Collection $orders;

    /**
     * @var Collection<int, PasswordResetToken>
     */
    #[ORM\OneToMany(targetEntity: PasswordResetToken::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $passwordResetTokens;

    public function getGsm(): ?string
    {
        return $this->gsm;
    }
    public function setGsm(?string $gsm): static
    {
        $this->gsm = $gsm;
        return $this;
    }

    public function getPostalAddress(): ?string
    {
        return $this->postalAddress;
    }
    public function setPostalAddress(?string $postalAddress): static
    {
        $this->postalAddress = $postalAddress;
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
            $order->setUser($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
{
    $this->orders->removeElement($order);
    return $this;
}

    /**
     * @return Collection<int, PasswordResetToken>
     */
    public function getPasswordResetTokens(): Collection
    {
        return $this->passwordResetTokens;
    }

    public function addPasswordResetToken(PasswordResetToken $passwordResetToken): static
    {
        if (!$this->passwordResetTokens->contains($passwordResetToken)) {
            $this->passwordResetTokens->add($passwordResetToken);
            $passwordResetToken->setUser($this);
        }

        return $this;
    }

    public function removePasswordResetToken(PasswordResetToken $passwordResetToken): static
    {
        if ($this->passwordResetTokens->removeElement($passwordResetToken)) {
            // set the owning side to null (unless already changed)
            if ($passwordResetToken->getUser() === $this) {
                $passwordResetToken->setUser(null);
            }
        }

        return $this;
    }
    #[ORM\Column(type: 'text', nullable: true)]
private ?string $allergies = null;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'userRef', orphanRemoval: true)]
    private Collection $reviews;

public function getAllergies(): ?string
{
    return $this->allergies;
}

public function setAllergies(?string $allergies): static
{
    $this->allergies = $allergies;
    return $this;
}

/**
 * @return Collection<int, Review>
 */
public function getReviews(): Collection
{
    return $this->reviews;
}

public function addReview(Review $review): static
{
    if (!$this->reviews->contains($review)) {
        $this->reviews->add($review);
        $review->setUserRef($this);
    }

    return $this;
}

public function removeReview(Review $review): static
{
    if ($this->reviews->removeElement($review)) {
        // set the owning side to null (unless already changed)
        if ($review->getUserRef() === $this) {
            $review->setUserRef(null);
        }
    }

    return $this;
}
#[ORM\Column]
private bool $isActive = true;

public function isActive(): bool
{
    return $this->isActive;
}

public function setIsActive(bool $isActive): static
{
    $this->isActive = $isActive;
    return $this;
}



}
