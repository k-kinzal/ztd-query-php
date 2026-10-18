<?php

declare(strict_types=1);

namespace HarborOps;

use HarborOps\Community\Communities;
use HarborOps\Import\{ImportAuditSubscriber, Importer};
use HarborOps\Moderation\{ReviewPolicy, Reviews};
use HarborOps\Notification\Digests;
use HarborOps\Privacy\Retention;
use HarborOps\Queue\{Queue,RetryPolicy,Worker};
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\{LockFactory,Store\PdoStore};
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\{Encoder\JsonEncoder, Normalizer\ObjectNormalizer, Serializer};
use Symfony\Component\Serializer\Mapping\{Factory\ClassMetadataFactory,Loader\AttributeLoader};
use Symfony\Component\Validator\Validation;

final class ApplicationFactory
{
    public function create(Database $database, ClockInterface $clock, MailerInterface $mailer, string $sender, string $operator): Application
    {
        $audit = new Audit($database, $clock, $operator);
        $communities = new Communities($database, $clock, $audit);
        $reviews = new Reviews($database, $clock, $audit, new ReviewPolicy());
        $queue = new Queue($database, $clock, new RetryPolicy());
        $digests = new Digests($database, $queue, $clock, $mailer, $sender);
        $retention = new Retention($database, $clock, $audit);
        $events = new EventDispatcher();
        $events->addSubscriber(new ImportAuditSubscriber($audit));
        $serializer = new Serializer([new ObjectNormalizer(new ClassMetadataFactory(new AttributeLoader()), null, null, new ReflectionExtractor())], [new JsonEncoder()]);
        $importer = new Importer($database, $clock, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(), $serializer, $events);
        $application = new Application('Harbor Operations', '1.0.0');
        $commands = [
            new Command\Migrate($database),new Command\CreateCommunity($communities),new Command\AddSource($communities),
            new Command\SubscribeDigest($communities),new Command\UnsubscribeDigest($communities),new Command\ImportPosts($importer),
            new Command\AddRule($reviews),new Command\ScanPosts($reviews),new Command\AssignCase($reviews),new Command\ResolveCase($reviews),new Command\OpenAppeal($reviews),new Command\DecideAppeal($reviews),new Command\ListCases($database),
            new Command\ScheduleDigests($digests),new Command\WorkQueue(new Worker($queue, $digests), new LockFactory(new PdoStore($database->pdo))),new Command\RetryJob($queue),
            new Command\RequestErasure($retention),new Command\ApproveErasure($retention),new Command\ExecuteErasure($retention),new Command\PurgeRetention($retention),
            new Command\ExportPosts(new Export\Exporter($database)),new Command\Status($database),
        ];
        foreach ($commands as $command) {
            $application->addCommand($command);
        }
        return $application;
    }
}
