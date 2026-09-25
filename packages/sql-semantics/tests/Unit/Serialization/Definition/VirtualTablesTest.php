<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\CreateVirtualTableStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\VirtualTables;

#[CoversClass(VirtualTables::class)]
#[Medium]
final class VirtualTablesTest extends TestCase
{
    public function testWritePreservesModuleArgumentTextVerbatim(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind('CREATE VIRTUAL TABLE temp.docs USING fts5(title, body, tokenize="porter ascii")');
        self::assertInstanceOf(CreateVirtualTableStatement::class, $statement);
        $expected = 'CREATE VIRTUAL TABLE "temp"."docs" USING "fts5"(title, body, tokenize="porter ascii")';
        self::assertSame($expected, VirtualTables::write($statement)->toString());
        $rebound = $binder->bind($expected);
        self::assertInstanceOf(CreateVirtualTableStatement::class, $rebound);
        self::assertSame('fts5', $rebound->constructor->module);
        self::assertSame(['title', 'body', 'tokenize="porter ascii"'], array_map(static fn ($argument): string => $argument->text, $rebound->constructor->arguments));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }

    public function testWriteOmitsTheArgumentListWhenTheModuleTakesNone(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind('CREATE VIRTUAL TABLE IF NOT EXISTS docs USING fts5');
        self::assertInstanceOf(CreateVirtualTableStatement::class, $statement);
        self::assertSame('CREATE VIRTUAL TABLE IF NOT EXISTS "docs" USING "fts5"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(CreateVirtualTableStatement::class, $rebound);
        self::assertTrue($rebound->ifNotExists);
        self::assertSame([], $rebound->constructor->arguments);
    }
}
