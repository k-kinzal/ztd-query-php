<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(CreateIndex::class)]
#[Medium]
final class CreateIndexTest extends TestCase
{
    public function testDeriveStatementResolvesTheKeyPartsInTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $table);
        $index = $semantics->analyze('CREATE INDEX i ON t (c, (b * 2))', [$table]);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $index);
        $indexStatement = $index->statement;
        self::assertInstanceOf(CreateIndex::class, $indexStatement);
        $part = $indexStatement->parts[1];

        self::assertInstanceOf(M\Table\Key\ExpressionPart::class, $part);
        self::assertInstanceOf(M\Expression\Operator\Arithmetic::class, $part->expression);
        $resolution = $index->facts->scalar($part->expression->left)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($table->declarations()[0]->columns[1], $resolution->slot->column);
        self::assertInstanceOf(UnknownKeyColumn::class, $index->facts->diagnostics[0]);
    }

    public function testDeriveRelationResolvesTheIndexedTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $table);
        $index = $semantics->analyze('CREATE FULLTEXT INDEX i ON t (a)', [$table]);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $index);
        $indexStatement = $index->statement;
        self::assertInstanceOf(CreateIndex::class, $indexStatement);
        $fact = $index->facts->relation($indexStatement)->table;

        self::assertInstanceOf(DeclaredTable::class, $fact);
        self::assertSame($table->declarations()[0], $fact->table);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('CREATE UNIQUE INDEX i USING BTREE ON db.t (a(2) DESC) COMMENT \'x\' INVISIBLE', (new Semantics(Dialect::MySql))->analyze('CREATE UNIQUE INDEX i TYPE BTREE ON db.t (a(2) DESC) COMMENT \'x\' INVISIBLE')->toString());
    }

    public function testDeriveStatementRefusesAView(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $table);
        $view = $semantics->analyze('CREATE VIEW v AS SELECT a FROM t', [$table]);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $view);

        $operation = $semantics->analyze('CREATE INDEX i ON v (a)', [$table, $view]);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        self::assertSame(['v is not BASE TABLE.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }
    public function testHasExecutionConditionRetainsTheExactOperand(): void
    {
        $part = new M\Table\Key\Deferred\ExpressionPart(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]));
        $definition = new CreateIndex(new Name('i'), new QualifiedName(new Name('t')), [$part]);
        self::assertSame([$part], $definition->parts);
        self::assertTrue($definition->hasExecutionCondition());
    }

    public function testDeriveStatementStoredBindsColumnsWithinTheRetainedRow(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(a INT)');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $table);
        $operation = $semantics->analyze('CREATE PROCEDURE p() CREATE INDEX i ON t ((ROW(a, 2)))', [$table]);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(CreateIndex::class, $operation->statement->body);
        $part = $operation->statement->body->parts[0];
        self::assertInstanceOf(M\Table\Key\Deferred\ExpressionPart::class, $part);
        self::assertInstanceOf(M\Expression\RowOperand::class, $part->expression);
        $items = (new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes())->parts($part->expression);
        self::assertNotNull($items);
        self::assertInstanceOf(\SqlSemantics\Statement\Operand::class, $items[0]);
        self::assertInstanceOf(ResolvedColumn::class, $operation->facts->scalar($items[0])->resolution);
    }

    public function testDeriveRelationPreservesTheUnresolvedTarget(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('CREATE PROCEDURE p() CREATE INDEX i ON absent ((ROW(1,2)))', $semantics->context([]));
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(CreateIndex::class, $operation->statement->body);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\MissingTable::class, $operation->facts->relation($operation->statement->body)->table);
    }

    public function testRenderPreservesTheCompleteStoredDefinition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE INDEX i ON d.t ((ROW(1,2)))');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        self::assertSame('CREATE PROCEDURE p() CREATE INDEX i ON d.t ((ROW(1, 2)))', $operation->toString());
    }

}
