<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\VirtualTableBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(VirtualTableBinder::class)]
#[Medium]
final class VirtualTableBinderTest extends TestCase
{
    public function testBindRetainsTheModuleAndItsTextArguments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind("CREATE VIRTUAL TABLE IF NOT EXISTS main.ft USING fts5(a, b, tokenize = 'porter')");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\CreateVirtualTableStatement::class, $statement);
        self::assertSame(['main', 'ft'], $statement->name->parts);
        self::assertSame('fts5', $statement->constructor->module);
        self::assertSame(['a', 'b', "tokenize = 'porter'"], array_column($statement->constructor->arguments, 'text'));
        self::assertTrue($statement->ifNotExists);
        self::assertSame("CREATE VIRTUAL TABLE IF NOT EXISTS \"main\".\"ft\" USING \"fts5\"(a, b, tokenize = 'porter')", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindAcceptsAModuleWithoutArguments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('CREATE VIRTUAL TABLE ft USING fts5');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\CreateVirtualTableStatement::class, $statement);
        self::assertSame(['ft'], $statement->name->parts);
        self::assertSame([], $statement->constructor->arguments);
        self::assertFalse($statement->ifNotExists);
        self::assertSame('CREATE VIRTUAL TABLE "ft" USING "fts5"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
