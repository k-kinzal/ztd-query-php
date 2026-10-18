<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Report;
use App\Enum\ReportState;
use App\Message\EscalateReports;
use App\Service\{Audit,PublishingRules};
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class EscalateReportsHandler
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly Audit $audit, private readonly PublishingRules $rules)
    {
    }
    public function __invoke(EscalateReports $message): void
    {
        foreach ($this->em->getRepository(Report::class)->findBy(['status' => ReportState::Open,'escalatedAt' => null]) as $report) {
            if ($report->createdAt > new DateTimeImmutable('-'.$this->rules->reportDeadlineHours($report->category).' hours')) {
                continue;
            }
            $report->escalatedAt = new DateTimeImmutable();
            $this->audit->record(null, $report->post->topic->board->community, 'report.escalated', (string)$report->id, ['category' => $report->category]);
        }
        $this->em->flush();
    }
}
