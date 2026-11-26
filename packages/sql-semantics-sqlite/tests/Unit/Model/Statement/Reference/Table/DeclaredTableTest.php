<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Reference\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(DeclaredTable::class)]
#[Medium]
final class DeclaredTableTest extends TestCase
{
    public function testTableIsTheDeclarationObjectOfTheContext(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER)');
        $query = $semantics->inspect('SELECT a FROM t', [$table]);

        $resolution = $query->facts->relation($query->singleNamedInput())->table;

        self::assertInstanceOf(DeclaredTable::class, $resolution);
        self::assertSame($table->declarations()[0], $resolution->table);
    }

    public function testTableIsFoundThroughAQualifiedName(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER)');
        $query = $semantics->inspect('SELECT a FROM main.t', [$table]);

        self::assertInstanceOf(DeclaredTable::class, $query->facts->relation($query->singleNamedInput())->table);
    }
}
