<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Lowering\Utility\TransactionRule;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Begin;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Chaining;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Commit;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Rollback;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SavepointAction;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SavepointCommand;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\TransactionMode;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(TransactionRule::class)]
#[Medium]
final class TransactionRuleTest extends TestCase
{
    public function testStatementLowersEachCommand(): void
    {
        $semantics = new Inspection();
        self::assertSame(
            ['BEGIN', 'START TRANSACTION', 'END', 'COMMIT', 'ABORT', 'ROLLBACK', 'SAVEPOINT s', 'RELEASE s', 'RELEASE s', "PREPARE TRANSACTION 'x'", "COMMIT PREPARED 'x'", "ROLLBACK PREPARED 'x'"],
            [
                $semantics->inspect('BEGIN WORK')->toString(),
                $semantics->inspect('START TRANSACTION')->toString(),
                $semantics->inspect('END TRANSACTION')->toString(),
                $semantics->inspect('COMMIT WORK')->toString(),
                $semantics->inspect('ABORT')->toString(),
                $semantics->inspect('ROLLBACK')->toString(),
                $semantics->inspect('SAVEPOINT s')->toString(),
                $semantics->inspect('RELEASE SAVEPOINT s')->toString(),
                $semantics->inspect('RELEASE s')->toString(),
                $semantics->inspect("PREPARE TRANSACTION 'x'")->toString(),
                $semantics->inspect("COMMIT PREPARED 'x'")->toString(),
                $semantics->inspect("ROLLBACK PREPARED 'x'")->toString(),
            ],
        );
    }

    public function testRollbackToLowersBothSpellings(): void
    {
        $long = (new Inspection())->inspect('ROLLBACK WORK TO SAVEPOINT s')->statement;
        $short = (new Inspection())->inspect('ROLLBACK TO s')->statement;
        self::assertInstanceOf(SavepointCommand::class, $long);
        self::assertInstanceOf(SavepointCommand::class, $short);
        self::assertSame([SavepointAction::RollbackTo, 's'], [$long->action, $short->name->value]);
    }

    public function testWordAcceptsTheNoiseWords(): void
    {
        $semantics = new Inspection();
        self::assertSame(['COMMIT', 'COMMIT', 'COMMIT'], [$semantics->inspect('COMMIT WORK')->toString(), $semantics->inspect('COMMIT TRANSACTION')->toString(), $semantics->inspect('COMMIT')->toString()]);
    }

    public function testChainingLowersEachClause(): void
    {
        $semantics = new Inspection();
        $statements = [$semantics->inspect('COMMIT AND CHAIN')->statement, $semantics->inspect('ROLLBACK WORK AND NO CHAIN')->statement, $semantics->inspect('END')->statement];
        self::assertSame(
            [Chaining::Chain, Chaining::NoChain, null],
            array_map(static fn (object $statement): ?Chaining => $statement instanceof Commit || $statement instanceof Rollback ? $statement->chaining : null, $statements),
        );
    }

    public function testOptionalModesIsEmptyWithoutModes(): void
    {
        $statement = (new Inspection())->inspect('BEGIN TRANSACTION')->statement;
        self::assertInstanceOf(Begin::class, $statement);
        self::assertSame([], $statement->modes);
    }

    public function testModesKeepsTheOrderWithOrWithoutCommas(): void
    {
        $statement = (new Inspection())->inspect('START TRANSACTION READ ONLY, DEFERRABLE ISOLATION LEVEL READ COMMITTED')->statement;
        self::assertInstanceOf(Begin::class, $statement);
        self::assertSame([TransactionMode::ReadOnly, TransactionMode::Deferrable, TransactionMode::ReadCommitted], $statement->modes);
    }

    public function testModeLowersEachMode(): void
    {
        $statement = (new Inspection())->inspect('BEGIN ISOLATION LEVEL READ UNCOMMITTED, ISOLATION LEVEL REPEATABLE READ, ISOLATION LEVEL SERIALIZABLE, READ WRITE, NOT DEFERRABLE')->statement;
        self::assertInstanceOf(Begin::class, $statement);
        self::assertSame([TransactionMode::ReadUncommitted, TransactionMode::RepeatableRead, TransactionMode::Serializable, TransactionMode::ReadWrite, TransactionMode::NotDeferrable], $statement->modes);
    }
}
