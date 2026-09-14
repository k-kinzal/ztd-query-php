<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\ColumnParser as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\TypeParameters::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
final class ColumnParserTest extends TestCase
{
    public function testParseColumnDefinitionPreservesFlagsAndDimensions(): void
    {
        $sql = 'CREATE TABLE users (id INT NOT NULL PRIMARY KEY, amount DECIMAL(8, 2) UNSIGNED DEFAULT 12.5)';
        $parser = new \PhpMyAdmin\SqlParser\Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $statement);
        self::assertIsArray($statement->fields);
        $column = (new Subject())->parseColumnDefinition($statement->fields[1], 'amount', []);
        self::assertNotNull($column);
        self::assertSame('DECIMAL', $column->type);
        self::assertTrue($column->unsigned);
        self::assertSame(8, $column->precision);
        self::assertSame(2, $column->scale);
        self::assertSame(12.5, $column->default);
    }
}
