<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\{Board,Community,Post,Sanction,Topic,User};
use App\Enum\{BoardVisibility,TopicState};
use App\Repository\MembershipRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class Access
{
    public function __construct(private readonly MembershipRepository $memberships, private readonly EntityManagerInterface $em)
    {
    }
    public function viewBoard(Board $board, ?User $user): bool
    {
        return match($board->visibility) {
            BoardVisibility::Public => true,BoardVisibility::Members => $user?->active() && $this->memberships->role($board->community, $user) !== null,BoardVisibility::Staff => $this->memberships->staff($board->community, $user)
        };
    }
    public function publish(Board $board, User $user): bool
    {
        return $user->active() && $user->verifiedAt !== null && $board->archivedAt === null && $this->viewBoard($board, $user) && $this->memberships->role($board->community, $user) !== null
            && (int)$this->em->createQueryBuilder()->select('COUNT(s.id)')->from(Sanction::class, 's')->where('s.community=:community AND s.user=:user AND s.revokedAt IS NULL AND s.expiresAt>:now')->setParameter('community', $board->community)->setParameter('user', $user)->setParameter('now', new DateTimeImmutable())->getQuery()->getSingleScalarResult() === 0;
    }
    public function viewTopic(Topic $topic, ?User $user): bool
    {
        return $this->viewBoard($topic->board, $user) && (in_array($topic->status, [TopicState::Published,TopicState::Locked,TopicState::Archived], true) || ($user !== null && ($topic->author->id === $user->id || $this->moderate($topic->board->community, $user))));
    }
    public function moderate(Community $community, ?User $user): bool
    {
        return $this->memberships->staff($community, $user);
    }
    public function edit(Post $post, User $user): bool
    {
        return $post->hiddenAt === null && $this->publish($post->topic->board, $user) && ($this->moderate($post->topic->board->community, $user) || ($post->author->id === $user->id && $post->topic->status === TopicState::Published && $post->createdAt > new DateTimeImmutable('-30 minutes')));
    }
}
