<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\{Board,Community,Post,Topic,User};
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ForumVoter extends Voter
{
    public function __construct(private readonly Access $access)
    {
    }
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, ['VIEW','PUBLISH','EDIT','MODERATE'], true) && ($subject instanceof Board || $subject instanceof Topic || $subject instanceof Post || $subject instanceof Community);
    }
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();
        $user = $user instanceof User ? $user : null;
        return match(true) {
            $attribute === 'VIEW' && $subject instanceof Board => $this->access->viewBoard($subject, $user),
            $attribute === 'VIEW' && $subject instanceof Topic => $this->access->viewTopic($subject, $user),
            $attribute === 'VIEW' && $subject instanceof Post => $subject->hiddenAt === null && $this->access->viewTopic($subject->topic, $user),
            $attribute === 'PUBLISH' && $subject instanceof Board => $user !== null && $this->access->publish($subject, $user),
            $attribute === 'EDIT' && $subject instanceof Post => $user !== null && $this->access->edit($subject, $user),
            $attribute === 'MODERATE' && $subject instanceof Community => $this->access->moderate($subject, $user),
            $attribute === 'MODERATE' && $subject instanceof Topic => $this->access->moderate($subject->board->community, $user),
            default => false,
        };
    }
}
