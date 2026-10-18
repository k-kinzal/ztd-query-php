<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'sanctions')]
#[ORM\Index(name:'sanction_expiry', columns:['community_id','user_id','expires_at'])]
class Sanction
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id = null;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $createdAt;
    #[ORM\Column(type:'text')] public string $reason;
    #[ORM\ManyToOne(targetEntity:Community::class),ORM\JoinColumn(nullable:false)] public Community $community;
    #[ORM\ManyToOne(targetEntity:User::class),ORM\JoinColumn(nullable:false)] public User $user;
    #[ORM\ManyToOne(targetEntity:User::class),ORM\JoinColumn(nullable:false)] public User $moderator;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $expiresAt;
    #[ORM\Column(type:'datetime_immutable', nullable:true)] public ?DateTimeImmutable $revokedAt = null;
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
