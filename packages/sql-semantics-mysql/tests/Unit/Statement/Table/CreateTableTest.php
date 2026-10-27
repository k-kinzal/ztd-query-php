<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\MultiplePrimaryKeys;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(CreateTable::class)]
#[Medium]
final class CreateTableTest extends TestCase
{
    public function testTemporaryTellsWhetherTemporaryIsWritten(): void
    {
        $twiceAnalysis = (new Semantics(Dialect::MySql, '5.7.44'))->analyze('CREATE TEMPORARY TEMPORARY TABLE t (a INT)');
        self::assertInstanceOf(M\Statement::class, $twiceAnalysis);
        $twice = $twiceAnalysis;
        $neverAnalysis = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT)');
        self::assertInstanceOf(M\Statement::class, $neverAnalysis);
        $never = $neverAnalysis;
        self::assertInstanceOf(CreateTable::class, $twice);
        self::assertInstanceOf(CreateTable::class, $never);

        self::assertTrue($twice->temporary());
        self::assertSame(2, $twice->temporaryWords);
        self::assertFalse($never->temporary());
    }

    public function testDeriveStatementDerivesTheElementsInTheTable(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, b INT, CHECK (a < b))');
        self::assertInstanceOf(M\Statement::class, $create);
        $statement = $create;
        self::assertInstanceOf(CreateTable::class, $statement);
        $check = $statement->elements[2];

        self::assertInstanceOf(M\Table\Key\CheckConstraint::class, $check);
        self::assertInstanceOf(M\Expression\Comparison::class, $check->condition);
        $resolution = $check->condition->right->scalarInformation()->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($create->declarations()[0]->columns[1], $resolution->slot->column);
    }

    public function testDeriveStatementReportsTheProblemsOfOrdinaryAndStoredDefinitions(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TABLE t ENGINE = InnoDB');
        self::assertInstanceOf(M\Statement::class, $create);
        $keys = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TABLE t (a INT PRIMARY KEY, PRIMARY KEY (a))');
        self::assertInstanceOf(M\Statement::class, $keys);

        self::assertInstanceOf(NoColumns::class, $create->diagnostics()[0]);
        self::assertInstanceOf(MultiplePrimaryKeys::class, $keys->diagnostics()[0]);
    }

    public function testDeriveRelationProvidesTheDeclaration(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (b INT NOT NULL, c TEXT) SELECT 1 AS a, 2 AS b');
        self::assertInstanceOf(M\Statement::class, $create);
        $statement = $create;
        self::assertInstanceOf(CreateTable::class, $statement);
        $table = $create->declarations()[0];
        $fact = $statement->relationInformation();

        self::assertInstanceOf(DeclaredTable::class, $fact->table);
        self::assertSame($table, $fact->table->table);
        self::assertSame(['c', 'a', 'b'], [$table->columns[0]->name->value, $table->columns[1]->name->value, $table->columns[2]->name->value]);
        self::assertSame(Nullability::NotNull, $table->columns[2]->nullability);
    }

    public function testRenderWritesTheDefinition(): void
    {
        self::assertSame('CREATE TEMPORARY TABLE IF NOT EXISTS db.t (a INT) ENGINE InnoDB COMMENT \'x\' REPLACE AS SELECT 1 AS a', (new Semantics(Dialect::MySql))->analyze('CREATE TEMPORARY TABLE IF NOT EXISTS db.t (a INT) ENGINE = InnoDB COMMENT = \'x\' REPLACE AS SELECT 1 AS a')->toString());
    }
    public function testInputCompletionOrdersTypeNoticesBeforeTheRefusal(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $parser = $semantics->parser();
        self::assertInstanceOf(\SqlParser\Parser\ReductionParser::class, $parser);
        $sql = 'CREATE TABLE t(a INT(11), b BIT(65) DEFAULT (!1), c INT(11))';
        $notices = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed($parser, $sql, strlen($sql), $semantics->profile());

        self::assertSame(['Integer display width is deprecated and will be removed in a future release.', 'Integer display width is deprecated and will be removed in a future release.', "Display width out of range for column 'b' (max = 64)"], array_map(static fn ($warning): string => $warning instanceof M\Notice\ParseStop ? $warning->name : ($warning instanceof M\Notice\Deprecated ? $warning->value : $warning->message()), array_column($notices, 1)));
    }

    public function testHasExecutionConditionRetainsTheOriginalHeaderRole(): void
    {
        $query = new M\Table\TableQuery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]));
        $definition = new CreateTable(new QualifiedName(new Name('t')), options: [new M\Table\Option\UnionOption([])], query: $query, temporaryWords: 1);
        self::assertTrue($definition->temporary());
        self::assertTrue($definition->hasExecutionCondition());
        self::assertTrue((new CreateTable($definition->name, options: $definition->options, query: $query))->hasExecutionCondition());
    }

    public function testDeriveProgramRetainsCompleteHeaderAndQueryOperands(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT) CREATE TABLE t(a INT, CHECK(a>0)) UNION=() AS SELECT x AS v');
        self::assertInstanceOf(M\Statement::class, $operation);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation);
        $definition = $operation->body;
        self::assertInstanceOf(CreateTable::class, $definition);
        self::assertCount(2, $definition->elements);
        self::assertNotNull($definition->query);
        self::assertTrue($definition->query->query->hasBinding());
    }

    public function testDeriveRelationRetainsTheProjectedColumnShape(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TABLE t UNION=() AS SELECT 1 AS v');
        self::assertInstanceOf(M\Statement::class, $operation);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(CreateTable::class, $operation->body);
        self::assertEquals(new Name('v'), $operation->body->relationInformation()->shape->slots[0]->name);
    }

    public function testRenderPreservesDeferredRowsAndMergeSources(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TEMPORARY TABLE IF NOT EXISTS t(a INT) ENGINE=InnoDB UNION=(s) AS SELECT ROW(1,2)');
        self::assertInstanceOf(M\Statement::class, $operation);
        self::assertSame('CREATE PROCEDURE p() CREATE TEMPORARY TABLE IF NOT EXISTS t (a INT) ENGINE InnoDB UNION (s) AS SELECT ROW(1, 2)', $operation->toString());
    }
}
