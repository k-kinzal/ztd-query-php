<?php

declare(strict_types=1);

namespace Tests\Integration\Statement;

use MySqlMemory\Command\Prepared\PreparedCache;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\Preparation;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Commit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Savepoint;

#[CoversNothing]
#[Medium]
final class TransactionSemanticExecutionTest extends TestCase
{
    #[DataProvider('providerReleases')]
    public function testAnalysisAndPreparationDoNotRunTheTransaction(GrammarRelease $release): void
    {
        $session = (new Instance(substr($release->value, 6)))->connect();
        $begin = $session->analyze('BEGIN WORK');
        self::assertInstanceOf(Begin::class, $begin);
        self::assertNotNull($begin->source);
        self::assertSame([0, 10], [$begin->source->offset, $begin->source->length]);
        self::assertFalse($session->transaction->active());
        (new Preparation())->check($begin, $session);
        $cache = PreparedCache::capture($begin, $session);
        self::assertFalse($session->transaction->active());
        self::assertSame([], $cache->tables);
        self::assertInstanceOf(\MySqlMemory\Command\Prepared\PreparedStatement::class, $cache->definition);
        self::assertSame($begin, $cache->definition->statement);
        $cache->definition->execute($session, 'This label is not SQL', []);
        self::assertTrue($session->transaction->active());
        self::assertSame(1, $session->instance->registry->status->read('Com_begin', $session->id));
        $savepoint = $session->analyze('SAVEPOINT `s p`');
        self::assertInstanceOf(Savepoint::class, $savepoint);
        self::assertSame([], $session->transaction->savepoints->list);
        (new Execution($session))->statement($savepoint);
        self::assertCount(1, $session->transaction->savepoints->list);
        $commit = $session->analyze('COMMIT');
        self::assertInstanceOf(Commit::class, $commit);
        self::assertTrue($session->transaction->active());
        (new Execution($session))->statement($commit);
        self::assertFalse($session->transaction->active());
        self::assertSame([], $session->transaction->savepoints->list);
    }

    #[DataProvider('providerReleases')]
    public function testConcreteStatementsKeepTheReleasesDiagnosticRetentionPolicy(GrammarRelease $release): void
    {
        $session = (new Instance(substr($release->value, 6)))->connect();
        $statement = $session->analyze('BEGIN');
        $session->diagnostics->warning(1105, 'Existing diagnostic');
        (new Execution($session))->statement($statement);
        self::assertSame($release === GrammarRelease::MySql5651 ? 1 : 0, $session->diagnostics->raised());
        self::assertSame([1, 0], $session->diagnostics->previous);
        self::assertSame([], $session->transaction->statements->running);
        self::assertSame([], $session->running->using);
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }

    #[DataProvider('providerReleases')]
    public function testSavepointNoticesBelongToReadingAndPreparationNotReplay(GrammarRelease $release): void
    {
        $session = (new Instance(substr($release->value, 6)))->connect();
        $session->execute('BEGIN');
        $statement = $session->analyze('SAVEPOINT FULL');
        self::assertInstanceOf(Savepoint::class, $statement);
        self::assertSame([], $session->diagnostics->conditions);
        $expected = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? [] : [['Warning', 4119, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::UnquotedFull->value]];
        (new Preparation())->check($statement, $session);
        self::assertSame($expected, $session->diagnostics->conditions);
        $cache = PreparedCache::capture($statement, $session);
        self::assertInstanceOf(\MySqlMemory\Command\Prepared\PreparedStatement::class, $cache->definition);
        $session->diagnostics->clear();
        $cache->definition->execute($session, 'not SQL', []);
        self::assertSame([], $session->diagnostics->conditions);
        (new Execution($session))->statement($statement);
        self::assertSame($expected, $session->diagnostics->conditions);
        $rollback = $session->analyze('ROLLBACK TO FULL');
        (new Execution($session))->statement($rollback);
        self::assertSame($expected, $session->diagnostics->conditions);
        $releaseSavepoint = $session->analyze('RELEASE SAVEPOINT FULL');
        (new Execution($session))->statement($releaseSavepoint);
        self::assertSame($expected, $session->diagnostics->conditions);
    }
}
