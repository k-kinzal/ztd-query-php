<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'digest_deliveries')]
#[ORM\UniqueConstraint(name:'digest_delivery_unique', columns:['user_id','period'])]
class DigestDelivery
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id = null;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $createdAt;
    #[ORM\Column(length:10)] public string $period;
    #[ORM\ManyToOne(targetEntity:User::class),ORM\JoinColumn(nullable:false)] public User $user;
    #[ORM\Column(type:'datetime_immutable', nullable:true)] public ?DateTimeImmutable $sentAt = null;
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
