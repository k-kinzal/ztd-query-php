<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Relation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Relation\TableReference::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TableReferenceTest extends TestCase
{
    public function testVisibleNameAndToStringKeepAliasAndDeclarationDistinct(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT b.foo FROM bar b');
        $table = $statement->tables[0];
        self::assertSame('b', $table->visibleName()->value);
        self::assertNotNull($table->declaration);
        self::assertSame('bar', $table->declaration->name->name->value);
        self::assertSame('bar AS b', $table->toString());
    }
    public function testToStringRetainsAnExplicitAlias(): void
    {
        self::assertSame('bar AS b', SemanticCases::select(Dialect::Sqlite, 'SELECT b.foo FROM bar b')->tables[0]->toString());
    }
}
