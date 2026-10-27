<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\Column\CollateAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;

#[CoversClass(CollateAttribute::class)]
#[Medium]
final class CollateAttributeTest extends TestCase
{
    public function testDeriveAttributeRetainsAKnownCollation(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a TEXT COLLATE utf8mb4_bin)');
        $statement = $create;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create;
        self::assertInstanceOf(CreateTable::class, $statement);
        $column = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);
        $attribute = $column->specification->columnAttributes()[0];

        self::assertInstanceOf(CollateAttribute::class, $attribute);
        self::assertSame('utf8mb4_bin', $attribute->collation->name?->value);
    }

    public function testDeriveAttributeReportsAnUnknownCollationBeforeTableResolution(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t MODIFY a BIT NULL COLLATE unknown');
        $problems = array_values(array_filter($operation->diagnostics(), static fn ($problem): bool => $problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation));

        self::assertCount(1, $problems);
        self::assertSame("Unknown collation: 'unknown'", $problems[0]->message());
    }

    public function testRenderWritesTheAttribute(): void
    {
        self::assertSame('CREATE TABLE t (a TEXT COLLATE utf8mb4_bin)', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a TEXT COLLATE utf8mb4_bin)')->toString());
    }
}
