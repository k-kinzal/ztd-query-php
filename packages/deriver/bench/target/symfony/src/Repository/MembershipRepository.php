<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{Community,Membership,User};
use App\Enum\MembershipRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class MembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Membership::class);
    }
    public function role(Community $community, ?User $user): ?MembershipRole
    {
        return $user === null ? null : $this->findOneBy(['community' => $community,'user' => $user])?->role;
    }
    public function staff(Community $community, ?User $user): bool
    {
        return $user?->active() && in_array($this->role($community, $user), [MembershipRole::Owner,MembershipRole::Moderator], true);
    }
}
