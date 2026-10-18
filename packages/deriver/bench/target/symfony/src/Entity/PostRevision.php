<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'post_revisions')]
class PostRevision
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id = null;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $createdAt;
    #[ORM\Column(type:'text')] public string $body;
    #[ORM\Column(length:500)] public string $reason;
    #[ORM\ManyToOne(targetEntity:Post::class),ORM\JoinColumn(nullable:false)] public Post $post;
    #[ORM\ManyToOne(targetEntity:User::class),ORM\JoinColumn(nullable:false)] public User $editor;
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
