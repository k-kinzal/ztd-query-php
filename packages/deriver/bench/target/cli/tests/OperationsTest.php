<?php

declare(strict_types=1);

namespace HarborOps\Tests;

use DomainException;
use HarborOps\{ApplicationFactory,Database};
use HarborOps\Queue\{Queue,RetryPolicy};
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\{Mailer,Transport\NullTransport};

final class OperationsTest extends TestCase
{
    private Database $database;
    private MockClock $clock;
    private Application $app;
    protected function setUp(): void
    {
        $this->database = new Database(new PDO('sqlite::memory:'));
        $this->database->migrate(dirname(__DIR__).'/database/001_operations.sql');
        $this->clock = new MockClock('2026-10-18T12:00:00+00:00');
        $this->operator('alice');
        $this->command('community:create', ['slug' => 'engineering','name' => 'Engineering']);
        $this->command('source:add', ['community' => '1','name' => 'support-api']);
    }
    private function operator(string $operator): void
    {
        $this->app = (new ApplicationFactory())->create($this->database, $this->clock, new Mailer(new NullTransport()), 'harbor@example.test', $operator);
    }
    private function command(string $name, array $arguments = [], int $status = 0): CommandTester
    {
        $tester = new CommandTester($this->app->find($name));
        $tester->execute($arguments);
        self::assertSame($status, $tester->getStatusCode(), $tester->getDisplay());
        return $tester;
    }
    private function import(string $body = 'A meaningful community support discussion.'): void
    {
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, json_encode(['id' => 'post-1','memberId' => 'user-1','email' => 'member@example.test','displayName' => 'Community member','title' => 'Operating the community safely','body' => $body,'publishedAt' => '2026-10-17T10:00:00+00:00'], JSON_THROW_ON_ERROR)."\n");
        rewind($stream);
        $input = new \Symfony\Component\Console\Input\ArrayInput(['source' => '1']);
        $input->setStream($stream);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        self::assertSame(0, $this->app->find('posts:import')->run($input, $output), $output->fetch());
        fclose($stream);
    }
    public function testImportIsIdempotentAndDigestDeliveryIsDurable(): void
    {
        $this->import();
        $this->import();
        self::assertSame(1, (int)$this->database->one('SELECT COUNT(*) AS n FROM posts')['n']);
        $this->command('digest:subscribe', ['member' => '1']);
        $this->command('digest:schedule', ['date' => '2026-10-17']);
        $this->command('digest:schedule', ['date' => '2026-10-17']);
        self::assertSame(1, (int)$this->database->one('SELECT COUNT(*) AS n FROM jobs')['n']);
        $this->command('queue:work');
        $this->command('queue:work');
        self::assertSame('done', $this->database->one('SELECT state FROM jobs')['state']);
        self::assertNotNull($this->database->one('SELECT sent_at FROM deliveries')['sent_at']);
    }
    public function testModerationAppealRequiresIndependentOperator(): void
    {
        $this->import('A message containing a stolen access token.');
        $this->command('rule:add', ['community' => '1','name' => 'Credential exposure','phrase' => 'access token','--severity' => 'urgent']);
        $this->command('moderation:scan');
        self::assertSame('quarantined', $this->database->one('SELECT state FROM posts')['state']);
        $this->command('case:claim', ['case' => '1']);
        $this->command('case:resolve', ['case' => '1','decision' => 'uphold','reason' => 'The message exposes another persons credential.']);
        self::assertSame('removed', $this->database->one('SELECT state FROM posts')['state']);
        $this->command('appeal:open', ['case' => '1','reason' => 'The token was a documented public example value.']);
        $this->command('appeal:decide', ['appeal' => '1','decision' => 'restore','reason' => 'Verified that the value is a public documentation token.'], 2);
        $this->operator('bob');
        $this->command('appeal:decide', ['appeal' => '1','decision' => 'restore','reason' => 'Verified that the value is a public documentation token.']);
        self::assertSame('published', $this->database->one('SELECT state FROM posts')['state']);
    }
    public function testLeaseRecoveryRejectsTheStaleWorker(): void
    {
        $queue = new Queue($this->database, $this->clock, new RetryPolicy());
        $queue->enqueue('digest', ['member_id' => 1,'period' => '2026-10-17'], 'lease-test');
        $stale = $queue->claim();
        self::assertNotNull($stale);
        self::assertNull($queue->claim());
        $this->clock->sleep(301);
        $current = $queue->claim();
        self::assertSame(2, $current['attempts']);
        $queue->complete($current);
        $this->expectException(DomainException::class);
        $queue->complete($stale);
    }
    public function testErasureRequiresSecondApprovalAndPreventsReimport(): void
    {
        $this->import();
        $this->command('privacy:request', ['member' => '1','reason' => 'Verified account owner requested permanent erasure.']);
        $this->command('privacy:approve', ['request' => '1'], 2);
        $this->operator('bob');
        $this->command('privacy:approve', ['request' => '1']);
        $this->command('privacy:erase', ['request' => '1']);
        self::assertSame('', $this->database->one('SELECT email FROM members')['email']);
        self::assertSame('[removed by author]', $this->database->one('SELECT body FROM posts')['body']);
        $this->command('privacy:erase', ['request' => '1'], 2);
    }
    public function testInvalidImportRollsBackEarlierRowsAndTheirAudit(): void
    {
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, json_encode(['id' => 'valid-1','memberId' => 'user-1','email' => 'member@example.test','displayName' => 'Member','title' => 'A valid support request','body' => 'A valid support request body.','publishedAt' => '2026-10-17T10:00:00+00:00'])."\n{invalid}\n");
        rewind($stream);
        $input = new \Symfony\Component\Console\Input\ArrayInput(['source' => '1']);
        $input->setStream($stream);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        self::assertSame(2, $this->app->find('posts:import')->run($input, $output));
        fclose($stream);
        self::assertSame([], $this->database->rows('SELECT * FROM posts'));
        self::assertSame([], $this->database->rows("SELECT * FROM audit WHERE action='post.imported'"));
    }
    public function testRetentionPreservesAnUnresolvedModerationCase(): void
    {
        $this->import('A message with disputed ownership rights.');
        $this->command('rule:add', ['community' => '1','name' => 'Ownership review','phrase' => 'ownership rights']);
        $this->command('moderation:scan');
        $this->clock->sleep(366 * 86400);
        $this->command('retention:purge');
        self::assertSame('A message with disputed ownership rights.', $this->database->one('SELECT body FROM posts')['body']);
        $this->command('case:claim', ['case' => '1']);
        $this->command('case:resolve', ['case' => '1','decision' => 'dismiss','reason' => 'The author has supplied valid ownership evidence.']);
        $this->command('retention:purge', ['--dry-run' => true]);
        self::assertSame('published', $this->database->one('SELECT state FROM posts')['state']);
        $this->command('retention:purge');
        self::assertSame('[expired]', $this->database->one('SELECT body FROM posts')['body']);
    }
    public function testCsvExportNeutralizesFormulasAfterLeadingWhitespace(): void
    {
        $this->import('  =SUM(1,1)');
        $output = $this->command('posts:export', ['community' => '1', '--format' => 'csv'])->getDisplay();
        $lines = explode("\n", trim($output));
        $row = str_getcsv($lines[1], ',', '"', '');
        self::assertSame("'  =SUM(1,1)", $row[3]);
        self::assertSame('  =SUM(1,1)', $this->database->one('SELECT body FROM posts')['body']);
    }

}
