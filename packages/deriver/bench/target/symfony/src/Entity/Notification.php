<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'notifications')]
#[ORM\UniqueConstraint(name:'notification_unique', columns:['user_id','event_key'])]
class Notification
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id = null;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $createdAt;
    #[ORM\Column(length:100)] public string $eventKey;
    #[ORM\Column(length:255)] public string $message;
    #[ORM\ManyToOne(targetEntity:Topic::class),ORM\JoinColumn(nullable:false)] public Topic $topic;
    #[ORM\ManyToOne(targetEntity:User::class),ORM\JoinColumn(nullable:false)] public User $user;
    #[ORM\Column(type:'datetime_immutable', nullable:true)] public ?DateTimeImmutable $readAt = null;
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
