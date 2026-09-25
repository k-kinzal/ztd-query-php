<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Foreign;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Foreign\TableTemplate::class)]
#[Medium]
final class TableTemplateTest extends TestCase
{
    public function testRetainsTheTemplateAndItsSelections(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN TABLE ft (LIKE app.t INCLUDING DEFAULTS EXCLUDING COMMENTS) SERVER s', strict: false);
        self::assertInstanceOf(CreateForeignTableStatement::class, $statement);
        self::assertEquals(new Foreign\TableTemplate(new QualifiedName(['app', 't']), [new Foreign\TemplateSelection(Foreign\TemplateProperty::Defaults, true), new Foreign\TemplateSelection(Foreign\TemplateProperty::Comments, false)]), $statement->templates[0]);
        self::assertSame('CREATE FOREIGN TABLE "public"."ft"(LIKE "app"."t" INCLUDING DEFAULTS EXCLUDING COMMENTS) SERVER "s"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnOverQualifiedTemplate(): void
    {
        $this->expectException(InvalidStructure::class);
        new Foreign\TableTemplate(new QualifiedName(['a', 'b', 'c', 'd']));
    }
}
