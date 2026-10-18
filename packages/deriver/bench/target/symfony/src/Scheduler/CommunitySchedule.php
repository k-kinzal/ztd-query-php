<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\{EscalateReports,PruneRetainedData,ScheduleDigests};
use DateTimeZone;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\{Attribute\AsSchedule,RecurringMessage,Schedule,ScheduleProviderInterface};
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('community')]
final class CommunitySchedule implements ScheduleProviderInterface
{
    public function __construct(#[Autowire(service:'cache.scheduler')] private readonly CacheInterface $state)
    {
    }
    public function getSchedule(): Schedule
    {
        return (new Schedule())->stateful($this->state)->add(RecurringMessage::every('15 minutes', new EscalateReports()), RecurringMessage::cron('10 3 * * *', new PruneRetainedData(), new DateTimeZone('UTC')), RecurringMessage::cron('0 7 * * *', new ScheduleDigests(), new DateTimeZone('UTC')));
    }
}
