<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;

#[CoversNothing]
final class SchemaParserInterfaceTest extends TestCase
{
    public function testParseAnswersTheTableTheStatementDescribes(): void
    {
        $schema = new TableSchema('users', ['id' => new ColumnDefinition('id', 'int')], ['id']);
        $parser = self::createStub(SchemaParserInterface::class);
        $parser->method('parse')->willReturn($schema);

        self::assertSame($schema, $parser->parse('CREATE TABLE users (id INT PRIMARY KEY)'));
    }
}
