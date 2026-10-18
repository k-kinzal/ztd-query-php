<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'audit_entries')]
#[ORM\Index(name:'audit_retention', columns:['created_at'])]
class AuditEntry
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id = null;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $createdAt;
    #[ORM\Column(length:80)] public string $action;
    #[ORM\Column(length:100)] public string $subject;
    #[ORM\ManyToOne(targetEntity:User::class),ORM\JoinColumn(nullable:true)] public ?User $actor = null;
    #[ORM\ManyToOne(targetEntity:Community::class),ORM\JoinColumn(nullable:true)] public ?Community $community = null;
    #[ORM\Column(type:'json')] public array $details = [];
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
