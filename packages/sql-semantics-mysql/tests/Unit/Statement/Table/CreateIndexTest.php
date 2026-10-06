<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;
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
        $index = $semantics->analyze('CREATE INDEX i ON t (c, (b * 2))', [$table]);
        $indexStatement = $index->statement;
        self::assertInstanceOf(CreateIndex::class, $indexStatement);
        $part = $indexStatement->parts[1];

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart::class, $part);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic::class, $part->expression);
        $resolution = $index->facts->scalar($part->expression->left)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($table->declarations()[0]->columns[1], $resolution->slot->column);
        self::assertInstanceOf(UnknownKeyColumn::class, $index->facts->diagnostics[0]);
    }

    public function testDeriveRelationResolvesTheIndexedTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $index = $semantics->analyze('CREATE FULLTEXT INDEX i ON t (a)', [$table]);
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
        $view = $semantics->analyze('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['v is not BASE TABLE.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE INDEX i ON v (a)', [$table, $view])->facts->diagnostics));
    }
}
