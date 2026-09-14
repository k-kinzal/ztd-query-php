<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Schema\ColumnParser as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\TypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
final class ColumnParserTest extends TestCase
{
    public function testParseColumnDefinitionReadsConstraintsAndDecimalShape(): void
    {
        $column = (new Subject())->parseColumnDefinition('amount NUMERIC(8, 2) NOT NULL DEFAULT 12.5', []);
        self::assertNotNull($column);
        self::assertSame('amount', $column->name);
        self::assertSame('NUMERIC', $column->type);
        self::assertSame(8, $column->precision);
        self::assertSame(2, $column->scale);
        self::assertFalse($column->nullable);
        self::assertSame(12.5, $column->default);
    }

    public function testParseColumnDefinitionAppliesTablePrimaryKey(): void
    {
        $column = (new Subject())->parseColumnDefinition('id INTEGER', ['id']);
        self::assertNotNull($column);
        self::assertFalse($column->nullable);
    }
}
