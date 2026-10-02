<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateIndex;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;

#[CoversClass(CreateIndex::class)]
#[Medium]
final class CreateIndexTest extends TestCase
{
    public function testIndexedGivesTheTableTheSchemaOfTheIndexName(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE INDEX aux.i ON t (a)')->statement;

        self::assertInstanceOf(CreateIndex::class, $statement);
        self::assertSame('aux', $statement->indexed()->schema?->value);
        self::assertSame('t', $statement->indexed()->name->value);
    }

    public function testDeriveStatementResolvesTheTableAndTheIndexedColumns(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a, b)');
        $operation = $semantics->analyze('CREATE INDEX i ON t (b, rowid) WHERE a > 0', [$table]);
        $statement = $operation->statement;

        self::assertInstanceOf(CreateIndex::class, $statement);
        $resolution = $operation->facts->relation($statement)->table;
        self::assertInstanceOf(DeclaredTable::class, $resolution);
        self::assertSame($table->declarations()[0], $resolution->table);
        $column = $operation->facts->scalar($statement->terms[0]->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $column);
        self::assertSame($table->declarations()[0]->columns[1], $column->slot->column);
        self::assertSame($statement, $column->relation);
        self::assertInstanceOf(ResolvedColumn::class, $operation->facts->scalar($statement->terms[1]->expression)->resolution);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame([], $operation->declarations());
    }

    public function testDeriveStatementReportsAMissingTableAndAMissingColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a)');

        self::assertInstanceOf(MissingTable::class, $semantics->analyze('CREATE INDEX i ON u (a)', [$table])->facts->diagnostics[0]);
        self::assertInstanceOf(MissingColumn::class, $semantics->analyze('CREATE INDEX i ON t (zz)', [$table])->facts->diagnostics[0]);
    }

    public function testDeriveRelationKeepsAnUndeclaredTableOpen(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $statement = $semantics->analyze('CREATE INDEX i ON t (a)')->statement;
        $derivation = new Derivation($semantics->context());

        self::assertInstanceOf(CreateIndex::class, $statement);
        $fact = $statement->deriveRelation($derivation, $derivation->environment());
        self::assertInstanceOf(UndeclaredTable::class, $fact->table);
        self::assertFalse($fact->shape->complete());
    }

    public function testRenderWritesEveryPartInGrammarOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create unique index if not exists main.i on t (a collate nocase desc, b + 1) where a is not null');

        self::assertSame('CREATE UNIQUE INDEX IF NOT EXISTS main.i ON t (a COLLATE nocase DESC, b + 1) WHERE a IS NOT NULL', $operation->toString());
    }
}
