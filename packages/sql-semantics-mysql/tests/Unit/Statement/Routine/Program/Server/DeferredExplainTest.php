<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain;

#[CoversClass(DeferredExplain::class)]
#[Medium]
final class DeferredExplainTest extends TestCase
{
    public function testDeriveProgramKeepsTheExplainedStatementAndItsBindings(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.1.0'))->analyze("CREATE PROCEDURE p() EXPLAIN FORMAT=JSON INTO @'' SELECT x FROM t");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(DeferredExplain::class, $operation->body);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation->body->statement);
        self::assertSame('', $operation->body->into?->name->value);
        self::assertNotInstanceOf(\SqlSemantics\Statement\Statement::class, $operation->body);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $operation->body->statement->query);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Relation\TableReference::class, $operation->body->statement->query->from);
        self::assertFalse($operation->body->statement->query->from->relationInformation()->shape->complete());
    }

    public function testRenderRetainsTheDeferredExplainedOperand(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.1.0'))->analyze("CREATE PROCEDURE p() EXPLAIN FORMAT=JSON INTO @'' SELECT ROW(1,2)");
        self::assertSame('CREATE PROCEDURE p() EXPLAIN FORMAT = JSON INTO @\'\' SELECT ROW(1, 2)', $operation->toString());
    }

    public function testDeriveProgramUsesForDatabaseWhileRetainingRoutineLocals(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-8.3.0');
        $table = $semantics->analyze('CREATE TABLE shop.t(a INT)');
        $operation = $semantics->analyze('CREATE PROCEDURE p(IN local_value INT) EXPLAIN ANALYZE FORMAT=TRADITIONAL FOR DATABASE shop SELECT a, local_value FROM t', [$table]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(DeferredExplain::class, $operation->body);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation->body->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $operation->body->statement->query);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Relation\TableReference::class, $operation->body->statement->query->from);
        $resolved = $operation->body->statement->query->from->relationInformation()->table;

        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $resolved);
        self::assertSame($table->declarations()[0], $resolved->table);
        self::assertSame([], $operation->diagnostics());
    }
    public function testRefusesAnOversizedDatabaseBeforeADeferredAnalysisRuns(): void
    {
        $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]));
        $this->expectExceptionMessage('An EXPLAIN database name has at most 64 characters.');
        new DeferredExplain($query, new \SqlSemantics\Statement\Identifier\Name('TRADITIONAL'), database: new \SqlSemantics\Statement\Identifier\Name(str_repeat('a', 65)), analyze: true);
    }

    public function testDeriveProgramIsNeverReachedForAnUnknownFormatInAStoredBody(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() EXPLAIN FORMAT=XML SELECT ROW(1,2)');
            self::fail('The format name is unknown.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\UnknownExplainFormat::class, $failure->problem);
            self::assertSame(39, $failure->end);
        }
    }

}
