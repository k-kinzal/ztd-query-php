<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\{CreateBoard,CreateCommunity};
use App\Entity\{Board,Community,Membership,User};
use App\Enum\{BoardVisibility,MembershipRole};
use App\Security\Access;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException,ConflictHttpException};

final class CommunityManagement
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly Access $access, private readonly Audit $audit)
    {
    }
    public function create(User $actor, CreateCommunity $input): Community
    {
        if (!$actor->active() || $actor->verifiedAt === null) {
            throw new AccessDeniedHttpException('Verify your account first.');
        }
        return $this->em->wrapInTransaction(function () use ($actor, $input): Community {
            if ($this->em->getRepository(Community::class)->findOneBy(['slug' => $input->slug])) {
                throw new ConflictHttpException('Community slug already exists.');
            }
            $community = new Community();
            $community->slug = $input->slug;
            $community->name = $input->name;
            $community->description = $input->description;
            $community->owner = $actor;
            $this->em->persist($community);
            $member = new Membership();
            $member->community = $community;
            $member->user = $actor;
            $member->role = MembershipRole::Owner;
            $this->em->persist($member);
            $this->audit->record($actor, $community, 'community.created', $input->slug);
            return $community;
        });
    }
    public function board(Community $community, User $actor, CreateBoard $input): Board
    {
        if (!$this->access->moderate($community, $actor)) {
            throw new AccessDeniedHttpException();
        }
        return $this->em->wrapInTransaction(function () use ($community, $actor, $input): Board {
            if ($this->em->getRepository(Board::class)->findOneBy(['community' => $community,'slug' => $input->slug])) {
                throw new ConflictHttpException('Board slug already exists.');
            }
            $board = new Board();
            $board->community = $community;
            $board->slug = $input->slug;
            $board->name = $input->name;
            $board->description = $input->description;
            $board->visibility = BoardVisibility::from($input->visibility);
            $board->requiresReview = $input->requiresReview;
            $this->em->persist($board);
            $this->audit->record($actor, $community, 'board.created', $input->slug);
            return $board;
        });
    }
    public function join(Community $community, User $user): void
    {
        if (!$user->active() || $user->verifiedAt === null) {
            throw new AccessDeniedHttpException('Verify your account first.');
        }
        if ($this->em->getRepository(Membership::class)->findOneBy(['community' => $community,'user' => $user])) {
            return;
        }
        $member = new Membership();
        $member->community = $community;
        $member->user = $user;
        $this->em->persist($member);
        $this->em->flush();
    }
    public function transfer(Community $community, User $actor, User $successor): void
    {
        if (!$actor->active() || $community->owner->id !== $actor->id) {
            throw new AccessDeniedHttpException('Only the owner can transfer ownership.');
        }
        $member = $this->em->getRepository(Membership::class)->findOneBy(['community' => $community,'user' => $successor]);
        if ($member === null || !$successor->active() || $successor->verifiedAt === null || $successor->id === $actor->id) {
            throw new ConflictHttpException('Choose another active verified member.');
        }
        $this->em->wrapInTransaction(function () use ($community, $actor, $successor, $member): void {
            $owner = $this->em->getRepository(Membership::class)->findOneBy(['community' => $community,'user' => $actor]);
            $owner->role = MembershipRole::Moderator;
            $member->role = MembershipRole::Owner;
            $community->owner = $successor;
            $this->audit->record($actor, $community, 'ownership.transferred', (string)$successor->id);
        });
    }
}
