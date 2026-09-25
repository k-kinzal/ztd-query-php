<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Module\ConstructorArgument;
use SqlSemantics\Model\Module\Invocation;
use SqlSemantics\Model\Statement\Definition\CreateVirtualTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateVirtualTableStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class CreateVirtualTableStatementTest extends TestCase
{
    public function testRetainsTheModuleAndArgumentOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('CREATE VIRTUAL TABLE temp.docs USING fts5(title, body, tokenize="porter ascii")');
        self::assertInstanceOf(CreateVirtualTableStatement::class, $statement);
        self::assertSame(['temp', 'docs'], $statement->name->parts);
        self::assertSame('fts5', $statement->constructor->module);
        self::assertSame(['title', 'body', 'tokenize="porter ascii"'], array_column($statement->constructor->arguments, 'text'));
    }

    public function testWithConstructorKeepsTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('CREATE VIRTUAL TABLE docs USING fts5(title)');
        self::assertInstanceOf(CreateVirtualTableStatement::class, $statement);
        $changed = $statement->withConstructor(new Invocation('fts5', [new ConstructorArgument('body')]));
        self::assertSame('CREATE VIRTUAL TABLE "docs" USING "fts5"(title)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame('CREATE VIRTUAL TABLE "docs" USING "fts5"(body)', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOriginKeepsTheModuleConstructor(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('CREATE VIRTUAL TABLE temp.docs USING fts5(title, body)');
        self::assertInstanceOf(CreateVirtualTableStatement::class, $statement);
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('other', $statement->source, Dialect::Sqlite, [], $statement->origin->context));
        self::assertSame('other', $changed->scopeId);
        self::assertSame($statement->constructor, $changed->constructor);
        self::assertSame($statement->name, $changed->name);
        self::assertSame('CREATE VIRTUAL TABLE "temp"."docs" USING "fts5"(title, body)', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }
}
