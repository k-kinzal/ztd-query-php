<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{AuditEntry,Community,User};
use Doctrine\ORM\EntityManagerInterface;

final class Audit
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }
    public function record(?User $actor, ?Community $community, string $action, string $subject, array $details = []): void
    {
        $entry = new AuditEntry();
        $entry->actor = $actor;
        $entry->community = $community;
        $entry->action = $action;
        $entry->subject = $subject;
        $entry->details = $details;
        $this->em->persist($entry);
    }
}
