<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaRelationOrder;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceName;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableColumn;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SchemaRelationOrder::class)]
final class SchemaRelationOrderTest extends TestCase
{
    public function testCheckRejectsAnUnguardedTableInTheEarlierSequenceSlot(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A repeated schema table requires its own IF NOT EXISTS guard.');

        (new Semantics())->analyze('CREATE SCHEMA batch CREATE TABLE ticket (id integer) CREATE SEQUENCE ticket');
    }

    public function testCheckRejectsASequenceFromAnotherNamespace(): void
    {
        $sequence = new CreateSequence(new SequenceName(new Identifier('elsewhere'), new Identifier('ticket')));
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A schema sequence must belong to its namespace and release.');

        new CreateSchema(new Identifier('batch'), sequences: [$sequence]);
    }

    public function testColumnKindDistinguishesTheTwoAdmittedIntegerWidths(): void
    {
        $name = new Identifier('amount');
        self::assertSame(ColumnKind::Int4, SchemaRelationOrder::columnKind(new TableColumn($name, IntegerStorage::Standard)));
        self::assertSame(ColumnKind::Int8, SchemaRelationOrder::columnKind(new TableColumn($name, IntegerStorage::Wide)));
        self::assertNull(SchemaRelationOrder::columnKind(new TableColumn($name, IntegerStorage::Small)));
    }
}
