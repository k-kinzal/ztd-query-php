<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'posts')]
#[ORM\Index(name:'post_thread', columns:['topic_id','id'])]
class Post
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id = null;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $createdAt;
    #[ORM\Column(type:'text')] public string $body;
    #[ORM\ManyToOne(targetEntity:Topic::class),ORM\JoinColumn(nullable:false)] public Topic $topic;
    #[ORM\ManyToOne(targetEntity:User::class),ORM\JoinColumn(nullable:false)] public User $author;
    #[ORM\Version,ORM\Column(type:'integer')] public int $version = 1;
    #[ORM\Column(type:'datetime_immutable', nullable:true)] public ?DateTimeImmutable $hiddenAt = null;
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
