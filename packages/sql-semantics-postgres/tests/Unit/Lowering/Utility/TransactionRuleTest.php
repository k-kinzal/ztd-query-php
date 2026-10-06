<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Lowering\Utility\TransactionRule;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Begin;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Chaining;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Commit;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Rollback;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SavepointAction;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SavepointCommand;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\TransactionMode;

#[CoversClass(TransactionRule::class)]
#[Medium]
final class TransactionRuleTest extends TestCase
{
    public function testStatementLowersEachCommand(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['BEGIN', 'START TRANSACTION', 'END', 'COMMIT', 'ABORT', 'ROLLBACK', 'SAVEPOINT s', 'RELEASE s', 'RELEASE s', "PREPARE TRANSACTION 'x'", "COMMIT PREPARED 'x'", "ROLLBACK PREPARED 'x'"],
            [
                $semantics->analyze('BEGIN WORK')->toString(),
                $semantics->analyze('START TRANSACTION')->toString(),
                $semantics->analyze('END TRANSACTION')->toString(),
                $semantics->analyze('COMMIT WORK')->toString(),
                $semantics->analyze('ABORT')->toString(),
                $semantics->analyze('ROLLBACK')->toString(),
                $semantics->analyze('SAVEPOINT s')->toString(),
                $semantics->analyze('RELEASE SAVEPOINT s')->toString(),
                $semantics->analyze('RELEASE s')->toString(),
                $semantics->analyze("PREPARE TRANSACTION 'x'")->toString(),
                $semantics->analyze("COMMIT PREPARED 'x'")->toString(),
                $semantics->analyze("ROLLBACK PREPARED 'x'")->toString(),
            ],
        );
    }

    public function testRollbackToLowersBothSpellings(): void
    {
        $long = (new Semantics(Dialect::PostgreSql))->analyze('ROLLBACK WORK TO SAVEPOINT s')->statement;
        $short = (new Semantics(Dialect::PostgreSql))->analyze('ROLLBACK TO s')->statement;
        self::assertInstanceOf(SavepointCommand::class, $long);
        self::assertInstanceOf(SavepointCommand::class, $short);
        self::assertSame([SavepointAction::RollbackTo, 's'], [$long->action, $short->name->value]);
    }

    public function testWordAcceptsTheNoiseWords(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(['COMMIT', 'COMMIT', 'COMMIT'], [$semantics->analyze('COMMIT WORK')->toString(), $semantics->analyze('COMMIT TRANSACTION')->toString(), $semantics->analyze('COMMIT')->toString()]);
    }

    public function testChainingLowersEachClause(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $statements = [$semantics->analyze('COMMIT AND CHAIN')->statement, $semantics->analyze('ROLLBACK WORK AND NO CHAIN')->statement, $semantics->analyze('END')->statement];
        self::assertSame(
            [Chaining::Chain, Chaining::NoChain, null],
            array_map(static fn (object $statement): ?Chaining => $statement instanceof Commit || $statement instanceof Rollback ? $statement->chaining : null, $statements),
        );
    }

    public function testOptionalModesIsEmptyWithoutModes(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze('BEGIN TRANSACTION')->statement;
        self::assertInstanceOf(Begin::class, $statement);
        self::assertSame([], $statement->modes);
    }

    public function testModesKeepsTheOrderWithOrWithoutCommas(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze('START TRANSACTION READ ONLY, DEFERRABLE ISOLATION LEVEL READ COMMITTED')->statement;
        self::assertInstanceOf(Begin::class, $statement);
        self::assertSame([TransactionMode::ReadOnly, TransactionMode::Deferrable, TransactionMode::ReadCommitted], $statement->modes);
    }

    public function testModeLowersEachMode(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze('BEGIN ISOLATION LEVEL READ UNCOMMITTED, ISOLATION LEVEL REPEATABLE READ, ISOLATION LEVEL SERIALIZABLE, READ WRITE, NOT DEFERRABLE')->statement;
        self::assertInstanceOf(Begin::class, $statement);
        self::assertSame([TransactionMode::ReadUncommitted, TransactionMode::RepeatableRead, TransactionMode::Serializable, TransactionMode::ReadWrite, TransactionMode::NotDeferrable], $statement->modes);
    }
}
