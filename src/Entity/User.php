<?php

namespace App\Entity;

// User.php — Représente la table "user" en BDD avec toutes les infos d'un utilisateur (email, mot de passe, nom, prénom, etc.)

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;


#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
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
    private bool $isVerified = false;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Reservation::class)]
    private Collection $reservations;

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


    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function __construct()
    {
    $this->reservations = new ArrayCollection();
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }
    // GETTER et SETTER pour les propriétés supplémentaires
    // Le nom
    public function getFirstName(): ?string { 
        return $this->firstName; 
    }
    public function setFirstName(?string $firstName): static { 
        $this->firstName = $firstName; 
        return $this; 
    }

    // Le prénom
    public function getLastName(): ?string { 
        return $this->lastName; 
    }
    public function setLastName(?string $lastName): static { 
        $this->lastName = $lastName; 
        return $this; 
    }

    // L'adresse
    public function getAddress(): ?string { 
        return $this->address; }
    
        public function setAddress(?string $address): static { 
        $this->address = $address; 
        return $this; 
    }
    
    // Le numéro de téléphone
    public function getPhone(): ?string { 
        return $this->phone; 
    }
    public function setPhone(?string $phone): static { 
        $this->phone = $phone; 
        return $this; 
    }
    public function getReservations(): Collection { 
        return $this->reservations; 
    }
    public function addReservation(Reservation $r): static { 
        if (!$this->reservations->contains($r)) { 
            $this->reservations->add($r); $r->setUser($this); 
        } return $this; 
    }
    public function removeReservation(Reservation $r): static { 
        if ($this->reservations->removeElement($r)) 
            { if ($r->getUser() === $this) { 
                $r->setUser(null); } 
            } 
            return $this; 
            }
    }
