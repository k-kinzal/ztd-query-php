<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Server\ServerOptions;
use SqlSemantics\Model\Statement\Definition\MySql\Server\CreateServerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateServerStatement::class)]
#[Medium]
final class CreateServerStatementTest extends TestCase
{
    public function testWithOriginRetainsTheDefinition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE SERVER s FOREIGN DATA WRAPPER mysql OPTIONS (USER 'u')");
        self::assertInstanceOf(CreateServerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame("CREATE SERVER `s` FOREIGN DATA WRAPPER `mysql` OPTIONS(USER 'u')", (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE SERVER s FOREIGN DATA WRAPPER mysql OPTIONS (USER 'u')");
        self::assertInstanceOf(CreateServerStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNameKeepsTheOriginalAndRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE SERVER s FOREIGN DATA WRAPPER mysql OPTIONS (USER 'u')");
        self::assertInstanceOf(CreateServerStatement::class, $statement);
        self::assertSame(['s', 'a`b'], [$statement->name, $statement->withName('a`b')->name]);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithWrapperReplacesTheWrapper(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE SERVER s FOREIGN DATA WRAPPER mysql OPTIONS (USER 'u')");
        self::assertInstanceOf(CreateServerStatement::class, $statement);
        self::assertSame("CREATE SERVER `s` FOREIGN DATA WRAPPER `other` OPTIONS(USER 'u')", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withWrapper('other')));
        self::assertSame('mysql', $statement->wrapper);
    }

    public function testWithOptionsReplacesTheOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE SERVER s FOREIGN DATA WRAPPER mysql OPTIONS (USER 'u')");
        self::assertInstanceOf(CreateServerStatement::class, $statement);
        self::assertSame("CREATE SERVER `s` FOREIGN DATA WRAPPER `mysql` OPTIONS(HOST 'h', PORT 3306)", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOptions(new ServerOptions(host: 'h', port: 3306))));
        self::assertSame('u', $statement->options->user);
    }
}
