<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'publication_receipts')]
#[ORM\UniqueConstraint(name:'publication_key_unique', columns:['user_id','request_key'])]
class PublicationReceipt
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id = null;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $createdAt;
    #[ORM\Column(length:128)] public string $requestKey;
    #[ORM\Column(length:64)] public string $fingerprint;
    #[ORM\ManyToOne(targetEntity:User::class),ORM\JoinColumn(nullable:false)] public User $user;
    #[ORM\ManyToOne(targetEntity:Topic::class),ORM\JoinColumn(nullable:false)] public Topic $topic;
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
