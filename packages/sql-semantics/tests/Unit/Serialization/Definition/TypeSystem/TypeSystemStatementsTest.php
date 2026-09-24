<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\TypeSystem\TypeSystemStatements;

#[CoversClass(TypeSystemStatements::class)]
#[Medium]
final class TypeSystemStatementsTest extends TestCase
{
    public function testWriteRoutesTypeSystemDefinitions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertSame('CREATE DOMAIN "d" AS integer', TypeSystemStatements::write($binder->bind('CREATE DOMAIN d AS integer'))?->toString());
        self::assertSame('DROP CAST(integer AS text)', TypeSystemStatements::write($binder->bind('DROP CAST (integer AS text)'))?->toString());
        self::assertSame('ALTER COLLATION "c" REFRESH VERSION', TypeSystemStatements::write($binder->bind('ALTER COLLATION c REFRESH VERSION'))?->toString());
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION "c" DROP MAPPING FOR "word"', TypeSystemStatements::write($binder->bind('ALTER TEXT SEARCH CONFIGURATION c DROP MAPPING FOR word'))?->toString());
        self::assertNull(TypeSystemStatements::write($binder->bind('SELECT 1')));
    }
}
