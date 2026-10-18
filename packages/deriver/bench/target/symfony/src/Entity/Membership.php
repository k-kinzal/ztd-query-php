<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'memberships')]
#[ORM\UniqueConstraint(name: 'membership_unique', columns: ['community_id','user_id'])]
class Membership
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id = null;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $createdAt;
    #[ORM\ManyToOne(targetEntity:Community::class),ORM\JoinColumn(nullable:false)] public Community $community;
    #[ORM\ManyToOne(targetEntity:User::class),ORM\JoinColumn(nullable:false)] public User $user;
    #[ORM\Column(enumType: \App\Enum\MembershipRole::class)] public \App\Enum\MembershipRole $role = \App\Enum\MembershipRole::Member;
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
