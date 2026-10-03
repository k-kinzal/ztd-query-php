<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(DeclaredTable::class)]
#[Medium]
final class DeclaredTableTest extends TestCase
{
    public function testTableIsTheDeclarationObjectOfTheContext(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $query = $semantics->analyze('SELECT a FROM t', [$table]);

        $resolution = $query->facts->relation($query->singleNamedInput())->table;

        self::assertInstanceOf(DeclaredTable::class, $resolution);
        self::assertSame($table->declarations()[0], $resolution->table);
    }

    public function testTableIsFoundThroughAQualifiedName(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $query = $semantics->analyze('SELECT a FROM main.t', [$table]);

        self::assertInstanceOf(DeclaredTable::class, $query->facts->relation($query->singleNamedInput())->table);
    }
}
