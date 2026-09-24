<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Foreign;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Foreign\TemplateSelection::class)]
#[Medium]
final class TemplateSelectionTest extends TestCase
{
    public function testRetainsThePropertyAndItsDirection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN TABLE ft (LIKE t EXCLUDING ALL INCLUDING INDEXES) SERVER s', strict: false);
        self::assertInstanceOf(CreateForeignTableStatement::class, $statement);
        self::assertEquals([new Foreign\TemplateSelection(Foreign\TemplateProperty::All, false), new Foreign\TemplateSelection(Foreign\TemplateProperty::Indexes, true)], $statement->templates[0]->selections);
    }
}
