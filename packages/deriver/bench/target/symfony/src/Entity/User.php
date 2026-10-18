<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\{PasswordAuthenticatedUserInterface, UserInterface};

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'user_email_unique', columns: ['email'])]
final class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id = null;
    #[ORM\Column(length:254)] public string $email;
    #[ORM\Column(length:80)] public string $displayName;
    #[ORM\Column] private string $password = '';
    #[ORM\Column(type:'datetime_immutable', nullable:true)] public ?DateTimeImmutable $verifiedAt = null;
    #[ORM\Column(type:'datetime_immutable', nullable:true)] public ?DateTimeImmutable $suspendedUntil = null;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $createdAt;
    #[ORM\Column] public bool $digestEnabled = false;
    #[ORM\Column(length:64, nullable:true)] public ?string $verificationHash = null;
    #[ORM\Column(type:'datetime_immutable', nullable:true)] public ?DateTimeImmutable $verificationExpiresAt = null;
    public function __construct(string $email, string $name)
    {
        $this->email = strtolower($email);
        $this->displayName = $name;
        $this->createdAt = new DateTimeImmutable();
    }
    public function getUserIdentifier(): string
    {
        return $this->email;
    }
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }
    public function getPassword(): string
    {
        return $this->password;
    }
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }
    public function eraseCredentials(): void
    {
    }
    public function active(): bool
    {
        return $this->suspendedUntil === null || $this->suspendedUntil < new DateTimeImmutable();
    }
}
