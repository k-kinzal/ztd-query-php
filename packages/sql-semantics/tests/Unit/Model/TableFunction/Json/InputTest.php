<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\TableFunction\Json\Input;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Input::class)]
#[Medium]
final class InputTest extends TestCase
{
    public function testRetainsTheDocumentExpressionAndItsDeclaredFormat(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(data JSONB)')))->bind("SELECT j.* FROM t, JSON_TABLE (t.data FORMAT JSON, '$[*]' COLUMNS (n FOR ORDINALITY)) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->relations[1]);
        self::assertInstanceOf(JsonTable::class, $statement->relations[1]->table);
        $document = $statement->relations[1]->table->document;
        self::assertSame('data', $document->expression->columnBinding()?->column->name);
        self::assertSame(Format::Json, $document->format);
    }

    public function testHasNoFormatUnlessDeclared(): void
    {
        $input = new Input(Expression::literal('{}', Dialect::PostgreSql));
        self::assertNull($input->format);
        self::assertSame("'{}'", $input->expression->spelling());
    }
}
