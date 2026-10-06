<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Reference\Table\MissingTable;

#[CoversClass(MissingTable::class)]
#[Medium]
final class MissingTableTest extends TestCase
{
    public function testMessageNamesTheRelationACompleteContextDoesNotDeclare(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT a FROM t', []);

        $resolution = $query->facts->relation($query->singleNamedInput())->table;

        self::assertInstanceOf(MissingTable::class, $resolution);
        self::assertSame('Relation t does not exist.', $resolution->message());
        self::assertSame('t', $resolution->name->name->value);
        self::assertSame($resolution, $query->facts->diagnostics[0]);
    }

    public function testMessageOfAQualifiedNameSearchesThatSchemaOnly(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
        $query = $semantics->analyze('SELECT a FROM aux.t', [$table]);

        self::assertInstanceOf(MissingTable::class, $query->facts->relation($query->singleNamedInput())->table);
    }
}
