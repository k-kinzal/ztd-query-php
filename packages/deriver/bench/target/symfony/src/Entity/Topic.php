<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'topics')]
#[ORM\Index(name:'topic_activity', columns:['board_id','status','updated_at'])]
class Topic
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column] public ?int $id = null;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $createdAt;
    #[ORM\Column(length:200)] public string $title;
    #[ORM\ManyToOne(targetEntity:Board::class),ORM\JoinColumn(nullable:false)] public Board $board;
    #[ORM\ManyToOne(targetEntity:User::class),ORM\JoinColumn(nullable:false)] public User $author;
    #[ORM\Column(enumType: \App\Enum\TopicState::class)] public \App\Enum\TopicState $status = \App\Enum\TopicState::Pending;
    #[ORM\Version,ORM\Column(type:'integer')] public int $version = 1;
    #[ORM\Column(type:'datetime_immutable')] public DateTimeImmutable $updatedAt;
    public function getState(): string
    {
        return $this->status->value;
    }
    public function setState(string $state, array $context = []): void
    {
        $this->status = \App\Enum\TopicState::from($state);
    }
    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }
}
