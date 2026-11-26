<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CreateTableLike::class)]
#[Medium]
final class CreateTableLikeTest extends TestCase
{
    public function testDeriveStatementCopiesTheSourceDeclaration(): void
    {
        $semantics = new Inspection();
        $source = $semantics->diagnose('CREATE TABLE s (a INT NOT NULL, b INT INVISIBLE)');
        $copy = $semantics->diagnose('CREATE TABLE c LIKE s', [$source]);
        $table = $copy->declarations()[0];

        self::assertInstanceOf(DeclaredTable::class, $copy->facts->relation($copy->statement)->table);
        self::assertSame(['a', 'b'], [$table->columns[0]->name->value, $table->implicit[0]->column->name->value]);
        self::assertNotSame($source->declarations()[0]->columns[0], $table->columns[0]);
    }

    public function testDeriveStatementLeavesAnUndeclaredSourceIncomplete(): void
    {
        $create = (new Inspection())->diagnose('CREATE TABLE c LIKE s');

        self::assertFalse($create->declarations()[0]->complete);
    }

    public function testRenderWritesLikeWithoutParentheses(): void
    {
        self::assertSame('CREATE TABLE IF NOT EXISTS c LIKE db.s', (new Inspection())->diagnose('CREATE TABLE IF NOT EXISTS c (LIKE db.s)')->toString());
    }

    public function testDeriveStatementRefusesAViewSource(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');
        $view = $semantics->diagnose('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['v is not BASE TABLE.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->diagnose('CREATE TABLE c LIKE v', [$table, $view])->facts->diagnostics));
    }
}
