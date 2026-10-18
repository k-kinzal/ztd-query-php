<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'subscriptions')]
#[ORM\UniqueConstraint(name:'subscription_unique', columns:['topic_id','user_id'])]
class Subscription
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id = null;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $createdAt;
    #[ORM\ManyToOne(targetEntity:Topic::class),ORM\JoinColumn(nullable:false)] public Topic $topic;
    #[ORM\ManyToOne(targetEntity:User::class),ORM\JoinColumn(nullable:false)] public User $user;
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
