<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Concurrency\LockMode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Locking;
use MySqlMemory\Plan\Path\Source\SingleRow;
use MySqlMemory\Plan\Path\Transform\Lock;
use MySqlMemory\Plan\Planner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockedRowAction;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(Locking::class)]
#[Small]
final class LockingTest extends TestCase
{
    public function testLockPlansALockingReadOfTheTablesItsClausesName(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); CREATE TABLE u (a INT)');
        $operation = $session->analyze('SELECT * FROM t, u AS v WHERE t.a = v.a FOR SHARE OF v SKIP LOCKED');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $root = $planner->query($statement, null)->root;
        self::assertInstanceOf(\MySqlMemory\Plan\Path\Transform\Project::class, $root);
        $path = $root->input;

        self::assertInstanceOf(Lock::class, $path);
        self::assertSame([['u', LockMode::Shared, LockedRowAction::SkipLocked]], array_map(static fn (array $target): array => [$target[0]->table->definition->name, $target[2], $target[3]], $path->targets));
    }

    public function testLockLeavesAPlainReadAlone(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $operation = $session->analyze('SELECT 1');
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $input = new SingleRow();

        self::assertSame($input, (new Locking($planner))->lock(null, new Scope(), $input, null));
    }

    public function testLockRefusesForUpdateInAReadOnlyTransaction(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); START TRANSACTION READ ONLY; SELECT * FROM t FOR SHARE');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1792);

        $session->query('SELECT * FROM t FOR UPDATE');
    }

    public function testTargetLocksAnOccurrenceAsTheStatementOrItsClauseSays(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT * FROM t FOR UPDATE NOWAIT');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $scope = new Scope();
        self::assertNotNull($statement->from);
        $planner->relations->plan($statement->from, $scope);
        $id = array_key_first($scope->scans) ?? 0;
        $scan = $scope->scans[$id];

        self::assertSame([
            [$scan, 0, LockMode::Exclusive, LockedRowAction::Nowait],
            [$scan, 0, LockMode::Exclusive, null],
            [$scan, 0, LockMode::Shared, null],
            null,
        ], [
            (new Locking($planner))->target($statement, $scope, $id, $scan, null, [], $session->transaction),
            (new Locking($planner))->target($statement, $scope, $id, $scan, null, [$id => true], $session->transaction),
            (new Locking($planner))->target(null, $scope, $id, $scan, LockMode::Shared, [], $session->transaction),
            (new Locking($planner))->target(null, $scope, $id, $scan, null, [], $session->transaction),
        ]);
    }

    public function testClauseAnswersTheModeAndActionOfTheClauseCoveringAnOccurrence(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT * FROM t FOR UPDATE NOWAIT');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $scope = new Scope();
        self::assertNotNull($statement->from);
        $planner->relations->plan($statement->from, $scope);

        self::assertSame([[LockMode::Exclusive, LockedRowAction::Nowait], null], [(new Locking($planner))->clause($statement, $scope, array_key_first($scope->scans) ?? 0), (new Locking($planner))->clause(null, $scope, array_key_first($scope->scans) ?? 0)]);
    }

    public function testWritesTellsAStatementThatWritesRows(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $insert = $session->analyze('INSERT INTO t SELECT * FROM t');
        $select = $session->analyze('SELECT * FROM t');

        self::assertSame([true, false], [
            (new Locking(new Planner($insert->statement, $insert->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary)))->writes(),
            (new Locking(new Planner($select->statement, $select->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary)))->writes(),
        ]);
    }

    public function testTransactionAnswersTheTransactionOfTheSession(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $operation = $session->analyze('SELECT 1');
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        self::assertSame($session->transaction, (new Locking($planner))->transaction());
    }
}
