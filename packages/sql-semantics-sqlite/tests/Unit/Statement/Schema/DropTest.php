<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AbsentRelation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Drop;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\KindRefusal;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\WrongRelationKind;
use SqlSemantics\Platform\Sqlite\Statement\Schema\SchemaObjectKind;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(Drop::class)]
#[Medium]
final class DropTest extends TestCase
{
    public function testDeriveStatementResolvesADroppedTable(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a)');
        $operation = $semantics->analyze('DROP TABLE t', [$table]);
        $resolution = $operation->facts->relation($operation->statement)->table;

        self::assertInstanceOf(DeclaredTable::class, $resolution);
        self::assertSame($table->declarations()[0], $resolution->table);
        self::assertSame([], $operation->declarations());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveStatementReportsAMissingTableUnlessIfExistsIsWritten(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $strict = $semantics->analyze('DROP TABLE t', []);
        $tolerant = $semantics->analyze('DROP VIEW IF EXISTS t', []);

        self::assertInstanceOf(MissingTable::class, $strict->facts->diagnostics[0]);
        self::assertSame([], $tolerant->facts->diagnostics);
        self::assertInstanceOf(AbsentRelation::class, $tolerant->facts->relation($tolerant->statement)->table);
    }

    public function testDeriveStatementReportsTheDropOfARelationOfTheOtherKind(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $declarations = [$semantics->analyze('CREATE TABLE t (a)'), $semantics->analyze('CREATE VIEW v AS SELECT 1')];
        $table = $semantics->analyze('DROP TABLE IF EXISTS v', $declarations);
        $view = $semantics->analyze('DROP VIEW t', $declarations);

        self::assertInstanceOf(WrongRelationKind::class, $table->facts->diagnostics[0]);
        self::assertSame(KindRefusal::DropTable, $table->facts->diagnostics[0]->refusal);
        self::assertInstanceOf(WrongRelationKind::class, $view->facts->diagnostics[0]);
        self::assertSame(KindRefusal::DropView, $view->facts->diagnostics[0]->refusal);
        self::assertSame([], $semantics->analyze('DROP VIEW v', $declarations)->facts->diagnostics);
    }

    public function testDeriveStatementKeepsAnUndeclaredTableUndeclared(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('DROP TABLE IF EXISTS t');

        self::assertInstanceOf(UndeclaredTable::class, $operation->facts->relation($operation->statement)->table);
    }

    public function testDeriveStatementRecordsNothingForIndexesAndTriggers(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $index = $semantics->analyze('DROP INDEX i', []);
        $trigger = $semantics->analyze('DROP TRIGGER tr', []);

        self::assertFalse($index->facts->covers($index->statement));
        self::assertFalse($trigger->facts->covers($trigger->statement));
        self::assertSame([], $index->facts->diagnostics);
    }

    public function testRenderWritesTheObjectKindAndTheClause(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze('drop trigger if exists main.tr');

        self::assertInstanceOf(Drop::class, $operation->statement);
        self::assertSame(SchemaObjectKind::Trigger, $operation->statement->object);
        self::assertSame('DROP TRIGGER IF EXISTS main.tr', $operation->toString());
        self::assertSame('DROP INDEX i', $semantics->analyze('DROP INDEX i')->toString());
        self::assertSame('DROP VIEW v', $semantics->analyze('DROP VIEW v')->toString());
        self::assertSame('DROP TABLE t', $semantics->analyze('DROP TABLE t')->toString());
    }
}
