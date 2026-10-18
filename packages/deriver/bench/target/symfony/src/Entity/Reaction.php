<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'reactions')]
#[ORM\UniqueConstraint(name:'reaction_unique', columns:['post_id','user_id'])]
class Reaction
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id = null;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $createdAt;
    #[ORM\Column(length:20)] public string $kind;
    #[ORM\ManyToOne(targetEntity:Post::class),ORM\JoinColumn(nullable:false)] public Post $post;
    #[ORM\ManyToOne(targetEntity:User::class),ORM\JoinColumn(nullable:false)] public User $user;
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
