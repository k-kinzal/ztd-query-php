<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Json\PassingArgument;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PassingArgument::class)]
#[Medium]
final class PassingArgumentTest extends TestCase
{
    public function testAssociatesEachVariableWithItsExpressionAndFormat(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' PASSING '{}' FORMAT JSON AS doc, 2 AS k COLUMNS (n FOR ORDINALITY)) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        $passing = $statement->from->table->passing;
        self::assertSame(['doc', 'k'], array_column($passing, 'name'));
        self::assertSame("'{}'", $passing[0]->input->expression->spelling());
        self::assertSame(Format::Json, $passing[0]->input->format);
        self::assertSame('2', $passing[1]->input->expression->spelling());
        self::assertNull($passing[1]->input->format);
        self::assertSame('SELECT "j"."n" AS "n" FROM JSON_TABLE(\'[]\', \'$[*]\' PASSING \'{}\' FORMAT JSON AS "doc", 2 AS "k" COLUMNS("n" FOR ORDINALITY)) AS "j"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
