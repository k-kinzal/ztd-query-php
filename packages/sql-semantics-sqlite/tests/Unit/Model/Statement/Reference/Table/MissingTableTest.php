<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Reference\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(MissingTable::class)]
#[Medium]
final class MissingTableTest extends TestCase
{
    public function testMessageNamesTheRelationACompleteContextDoesNotDeclare(): void
    {
        $query = (new Semantics())->inspect('SELECT a FROM t', []);

        $resolution = $query->facts->relation($query->singleNamedInput())->table;

        self::assertInstanceOf(MissingTable::class, $resolution);
        self::assertSame('Relation t does not exist.', $resolution->message());
        self::assertSame('t', $resolution->name->name->value);
        self::assertSame($resolution, $query->facts->diagnostics[0]);
    }

    public function testMessageOfAQualifiedNameSearchesThatSchemaOnly(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE t (a INTEGER)');
        $query = $semantics->inspect('SELECT a FROM aux.t', [$table]);

        self::assertInstanceOf(MissingTable::class, $query->facts->relation($query->singleNamedInput())->table);
    }
}
