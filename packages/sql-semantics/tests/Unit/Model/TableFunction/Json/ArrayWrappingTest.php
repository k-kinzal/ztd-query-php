<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Json\ArrayWrapping;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Json\ValueColumn;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ArrayWrapping::class)]
#[Medium]
final class ArrayWrappingTest extends TestCase
{
    public function testRepresentsEveryWrapperPolicy(): void
    {
        self::assertSame(['', 'WITHOUT WRAPPER', 'WITH CONDITIONAL WRAPPER', 'WITH UNCONDITIONAL WRAPPER'], array_column(ArrayWrapping::cases(), 'value'));
    }

    #[TestWith(['WITH WRAPPER', ArrayWrapping::Unconditional, ' WITH UNCONDITIONAL WRAPPER'])]
    #[TestWith(['WITH UNCONDITIONAL ARRAY WRAPPER', ArrayWrapping::Unconditional, ' WITH UNCONDITIONAL WRAPPER'])]
    #[TestWith(['WITH CONDITIONAL WRAPPER', ArrayWrapping::Conditional, ' WITH CONDITIONAL WRAPPER'])]
    #[TestWith(['WITHOUT WRAPPER', ArrayWrapping::Without, ' WITHOUT WRAPPER'])]
    #[TestWith(['', ArrayWrapping::Default, ''])]
    public function testBindsThePolicyAndWritesItsCanonicalSpelling(string $clause, ArrayWrapping $wrapper, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (v INTEGER PATH '$.b' " . $clause . ')) AS j');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        self::assertInstanceOf(ValueColumn::class, $statement->from->table->columns[0]);
        self::assertSame($wrapper, $statement->from->table->columns[0]->wrapper);
        self::assertSame('SELECT "j"."v" AS "v" FROM JSON_TABLE(\'[]\', \'$[*]\' COLUMNS("v" integer PATH \'$.b\'' . $expected . ')) AS "j"', $statement->toString());
    }
}
