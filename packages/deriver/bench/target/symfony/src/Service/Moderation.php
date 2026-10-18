<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\{FileReport,ResolveReport,RestrictMember};
use App\Entity\{Community,Post,Report,Sanction,User};
use App\Enum\{MembershipRole,ReportState};
use App\Repository\MembershipRepository;
use App\Security\Access;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException,ConflictHttpException,NotFoundHttpException};

final class Moderation
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly Access $access, private readonly Audit $audit, private readonly MembershipRepository $memberships)
    {
    }
    public function report(Post $post, User $actor, FileReport $input): Report
    {
        if (!$actor->active() || $post->hiddenAt !== null || !$this->access->viewTopic($post->topic, $actor)) {
            throw new AccessDeniedHttpException();
        }
        $report = $this->em->getRepository(Report::class)->findOneBy(['post' => $post,'reporter' => $actor]);
        if ($report !== null) {
            return $report;
        }
        $report = new Report();
        $report->post = $post;
        $report->reporter = $actor;
        $report->category = $input->category;
        $report->reason = $input->reason;
        $this->em->persist($report);
        $this->em->flush();
        return $report;
    }
    public function resolve(Report $report, User $actor, ResolveReport $input): void
    {
        if (!$this->access->moderate($report->post->topic->board->community, $actor)) {
            throw new AccessDeniedHttpException();
        }
        if ($report->status !== ReportState::Open || in_array($actor->id, [$report->reporter->id,$report->post->author->id], true)) {
            throw new ConflictHttpException('An independent moderator must resolve an open report.');
        }
        $this->em->wrapInTransaction(function () use ($report, $actor, $input): void {
            $this->em->lock($report, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            $report->status = ReportState::from($input->decision);
            $report->resolution = $input->reason;
            $report->moderator = $actor;
            $report->resolvedAt = new DateTimeImmutable();
            if ($report->status === ReportState::Upheld) {
                $report->post->hiddenAt = new DateTimeImmutable();
            }
            $this->audit->record($actor, $report->post->topic->board->community, 'report.resolved', (string)$report->id, ['decision' => $input->decision,'reason' => $input->reason]);
        });
    }
    public function restrict(Community $community, User $actor, RestrictMember $input): Sanction
    {
        if (!$this->access->moderate($community, $actor)) {
            throw new AccessDeniedHttpException();
        }
        $member = $this->em->find(User::class, $input->userId) ?? throw new NotFoundHttpException();
        if ($this->memberships->role($community, $member) !== MembershipRole::Member) {
            throw new ConflictHttpException('Only ordinary members can be restricted.');
        }
        $sanction = new Sanction();
        $sanction->community = $community;
        $sanction->moderator = $actor;
        $sanction->user = $member;
        $sanction->reason = $input->reason;
        $sanction->expiresAt = new DateTimeImmutable('+'.$input->hours.' hours');
        $this->em->persist($sanction);
        $this->audit->record($actor, $community, 'member.restricted', (string)$member->id, ['hours' => $input->hours]);
        $this->em->flush();
        return $sanction;
    }
}
