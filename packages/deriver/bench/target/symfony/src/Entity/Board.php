<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'boards')]
#[ORM\UniqueConstraint(name: 'board_slug_unique', columns: ['community_id','slug'])]
class Board
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id = null;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $createdAt;
    #[ORM\Column(length:80)] public string $slug;
    #[ORM\Column(length:120)] public string $name;
    #[ORM\Column(type:'text')] public string $description;
    #[ORM\ManyToOne(targetEntity:Community::class),ORM\JoinColumn(nullable:false)] public Community $community;
    #[ORM\Column(enumType: \App\Enum\BoardVisibility::class)] public \App\Enum\BoardVisibility $visibility = \App\Enum\BoardVisibility::Public;
    #[ORM\Column] public bool $requiresReview = false;
    #[ORM\Column(type:'datetime_immutable', nullable:true)] public ?DateTimeImmutable $archivedAt = null;
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
