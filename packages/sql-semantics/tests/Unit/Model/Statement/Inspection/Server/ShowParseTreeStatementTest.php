<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowDatabasesStatement;
use SqlSemantics\Model\Statement\Inspection\Server\ShowParseTreeStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowParseTreeStatement::class)]
#[Medium]
final class ShowParseTreeStatementTest extends TestCase
{
    public function testResultColumnsReturnTheTreeAsJson(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE users(id INT)')))->bind('SHOW PARSE_TREE SELECT id FROM users');
        self::assertInstanceOf(ShowParseTreeStatement::class, $statement);
        self::assertSame('SELECT', $statement->statement->kind->value);
        self::assertSame(['Parse_tree'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('json', $statement->resultColumns()[0]->expression->type->name);
        self::assertSame('SHOW PARSE_TREE SELECT `id` AS `id` FROM `users`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithStatementParsesAnotherStatementImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build());
        $statement = $binder->bind('SHOW PARSE_TREE SELECT 1');
        $other = $binder->bind('SHOW DATABASES');
        self::assertInstanceOf(ShowParseTreeStatement::class, $statement);
        $changed = $statement->withStatement($other);
        self::assertNotSame($statement, $changed);
        self::assertSame('SELECT', $statement->statement->kind->value);
        self::assertInstanceOf(ShowDatabasesStatement::class, $changed->statement);
        self::assertSame('SHOW PARSE_TREE SHOW DATABASES', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOriginRetainsTheStatement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('SHOW PARSE_TREE SELECT 1');
        self::assertInstanceOf(ShowParseTreeStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->statement, $copy->statement);
    }

    public function testRejectsAStatementFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('SHOW PARSE_TREE SELECT 1');
        self::assertInstanceOf(ShowParseTreeStatement::class, $statement);
        $foreign = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new ShowParseTreeStatement($statement->origin, $foreign);
    }

    public function testRejectsGrammarsBefore81(): void
    {
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('SHOW DATABASES');
        self::assertInstanceOf(ShowDatabasesStatement::class, $legacy);
        $this->expectException(InvalidStructure::class);
        new ShowParseTreeStatement($legacy->origin, $legacy);
    }
}
