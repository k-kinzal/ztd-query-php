<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\Generators;
use Eris\TestTrait;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\XaState;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction as Transaction;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetTransaction;
use Tests\Generator\MySql\Statement\Server\Transaction\BeginGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\CommitGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\SavepointGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\TransactionStatementGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XidGenerator;
use Tests\Generator\MySql\Statement\Utility\Set\SetTransactionGenerator;

/**
 * Generated statements execute directly. Sequence relationships are rebuilt by
 * Eris during shrinking, before the execution callback sees the input.
 */
#[CoversNothing]
#[Large]
final class TransactionExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * SQL errors and successful replies both leave the execution journal balanced.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesEveryTransactionAlternativeUnwindsItsRuntimeScope(GrammarRelease $release): void
    {
        $this->forAll(new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, new TransactionStatementGenerator($release)))->withMaxSize(10)->then(static function (Transaction\TransactionStatement $statement) use ($release): void {
            $session = (new Instance(substr($release->value, 6)))->connect();
            $session->text = 'This is not SQL; execution must use the generated values.';
            $execution = new Execution($session);
            try {
                $reply = $execution->statement($statement);
                self::assertTrue($reply instanceof Completion || $reply instanceof ResultSet);
                self::assertSame($reply instanceof Completion ? $reply->affectedRows : -1, $session->variables->rowCount);
            } catch (SqlError $error) {
                $execution->failed($error);
                self::assertSame(-1, $session->variables->rowCount);
                self::assertGreaterThan(0, $session->diagnostics->errors());
            }
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }

    /**
     * A savepoint remains usable until its generated release and transaction completion.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesSavepointReferencesSurviveGeneratedSequenceShrinking(GrammarRelease $release): void
    {
        $sequences = Generators::map(static function (array $parts) use ($release): array {
            [$settings, $begin, $savepoint, $commit] = $parts;
            $profile = new LanguageProfile($release);
            return array_map(static fn (\SqlSemantics\Platform\MySql\Statement\Statement $statement): \SqlSemantics\Platform\MySql\Statement\Statement => (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish(new \SqlSemantics\Contract\AnalysisContext($profile, [new \SqlSemantics\Statement\Identifier\Name('pbt')]), $statement), [$settings, $begin, $savepoint, new Transaction\RollbackToSavepoint($savepoint->savepoint, profile: $profile), new Transaction\ReleaseSavepoint($savepoint->savepoint, profile: $profile), $commit]);
        }, \Tests\Generator\Operands::tuple(new SetTransactionGenerator($release, Generators::constant(null)), new BeginGenerator($release), new SavepointGenerator($release), new CommitGenerator($release)));
        $this->forAll($sequences)->withMaxSize(10)->then(static function (array $sequence) use ($release): void {
            [$settings, $begin, $savepoint, $rollback, $releaseSavepoint, $commit] = $sequence;
            self::assertInstanceOf(SetTransaction::class, $settings);
            self::assertInstanceOf(Transaction\Begin::class, $begin);
            self::assertInstanceOf(Transaction\Savepoint::class, $savepoint);
            self::assertInstanceOf(Transaction\RollbackToSavepoint::class, $rollback);
            self::assertInstanceOf(Transaction\ReleaseSavepoint::class, $releaseSavepoint);
            self::assertInstanceOf(Transaction\Commit::class, $commit);
            self::assertSame($savepoint->savepoint, $rollback->savepoint);
            self::assertSame($savepoint->savepoint, $releaseSavepoint->savepoint);
            $session = (new Instance(substr($release->value, 6)))->connect();
            $session->text = 'Generated transaction sequence without SQL input';
            $execution = new Execution($session);
            $execution->statement($settings);
            $isolation = $session->transaction->nextIsolation ?? $session->transaction->isolation;
            $readOnly = $session->transaction->nextReadOnly ?? false;
            $execution->statement($begin);
            self::assertTrue($session->transaction->active());
            self::assertSame($isolation, $session->transaction->isolation);
            self::assertSame($readOnly, $session->transaction->readOnly);
            $execution->statement($savepoint);
            self::assertCount(1, $session->transaction->savepoints->list);
            $execution->statement($rollback);
            self::assertCount(1, $session->transaction->savepoints->list);
            $execution->statement($releaseSavepoint);
            self::assertSame([], $session->transaction->savepoints->list);
            $reply = $execution->statement($commit);
            self::assertInstanceOf(Completion::class, $reply);
            self::assertSame(0, $reply->affectedRows);
            self::assertSame($commit->release === true, $session->released);
            if (!$session->released) {
                self::assertSame($commit->chain === true, $session->transaction->active());
            }
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * The prepared registry contains the same generated identifier until completion.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesXaPreparationAndCompletionKeepTheGeneratedIdentifier(GrammarRelease $release): void
    {
        $sequences = Generators::map(static function (array $parts) use ($release): array {
            [$xid, $commit] = $parts;
            $profile = new LanguageProfile($release);
            return array_map(static fn (\SqlSemantics\Platform\MySql\Statement\Statement $statement): \SqlSemantics\Platform\MySql\Statement\Statement => (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish(new \SqlSemantics\Contract\AnalysisContext($profile, [new \SqlSemantics\Statement\Identifier\Name('pbt')]), $statement), [new Xa\XaStart($xid, profile: $profile), new Xa\XaEnd($xid, profile: $profile), new Xa\XaPrepare($xid, profile: $profile), new Xa\XaRecover(profile: $profile), $commit ? new Xa\XaCommit($xid, profile: $profile) : new Xa\XaRollback($xid, profile: $profile)]);
        }, \Tests\Generator\Operands::tuple(new XidGenerator(), Generators::bool()));
        $this->forAll($sequences)->withMaxSize(10)->then(static function (array $sequence) use ($release): void {
            [$start, $end, $prepare, $recover, $finish] = $sequence;
            self::assertInstanceOf(Xa\XaStart::class, $start);
            self::assertInstanceOf(Xa\XaEnd::class, $end);
            self::assertInstanceOf(Xa\XaPrepare::class, $prepare);
            self::assertInstanceOf(Xa\XaRecover::class, $recover);
            self::assertTrue($finish instanceof Xa\XaCommit || $finish instanceof Xa\XaRollback);
            self::assertEquals($start->xid, $end->xid);
            self::assertEquals($start->xid, $prepare->xid);
            $session = (new Instance(substr($release->value, 6)))->connect();
            $session->text = 'Generated XA sequence without SQL input';
            $execution = new Execution($session);
            $execution->statement($start);
            self::assertSame(XaState::Active, $session->transaction->xa);
            $execution->statement($end);
            self::assertSame(XaState::Idle, $session->transaction->xa);
            $execution->statement($prepare);
            self::assertCount(1, $session->instance->registry->prepared);
            $rows = $execution->statement($recover);
            self::assertInstanceOf(ResultSet::class, $rows);
            self::assertCount(1, $rows->rows);
            $branch = array_values($session->instance->registry->prepared)[0];
            self::assertSame($start->xid->transaction->value, $branch->transaction);
            self::assertSame($start->xid->branch === null ? '' : $start->xid->branch->value, $branch->branch);
            $execution->statement($finish);
            self::assertSame([], $session->instance->registry->prepared);
            $rows = $execution->statement($recover);
            self::assertInstanceOf(ResultSet::class, $rows);
            self::assertSame([], $rows->rows);
            self::assertSame([], $session->transaction->statements->running);
        });
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
}
