<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'reports')]
#[ORM\UniqueConstraint(name:'report_unique', columns:['post_id','reporter_id'])]
class Report
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id = null;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $createdAt;
    #[ORM\Column(length:30)] public string $category;
    #[ORM\Column(type:'text')] public string $reason;
    #[ORM\ManyToOne(targetEntity:Post::class),ORM\JoinColumn(nullable:false)] public Post $post;
    #[ORM\ManyToOne(targetEntity:User::class),ORM\JoinColumn(nullable:false)] public User $reporter;
    #[ORM\ManyToOne(targetEntity:User::class),ORM\JoinColumn(nullable:true)] public ?User $moderator = null;
    #[ORM\Column(enumType: \App\Enum\ReportState::class)] public \App\Enum\ReportState $status = \App\Enum\ReportState::Open;
    #[ORM\Column(type:'text', nullable:true)] public ?string $resolution = null;
    #[ORM\Column(type:'datetime_immutable', nullable:true)] public ?DateTimeImmutable $resolvedAt = null;
    #[ORM\Column(type:'datetime_immutable', nullable:true)] public ?DateTimeImmutable $escalatedAt = null;
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
