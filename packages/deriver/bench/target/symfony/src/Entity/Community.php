<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'communities')]
#[ORM\UniqueConstraint(name: 'community_slug_unique', columns: ['slug'])]
class Community
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id = null;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $createdAt;
    #[ORM\Column(length:80)] public string $slug;
    #[ORM\Column(length:120)] public string $name;
    #[ORM\Column(type:'text')] public string $description;
    #[ORM\ManyToOne(targetEntity:User::class),ORM\JoinColumn(nullable:false)] public User $owner;
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
