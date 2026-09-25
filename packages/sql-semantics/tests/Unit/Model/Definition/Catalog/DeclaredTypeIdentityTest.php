<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(Catalog\DeclaredTypeIdentity::class)]
#[Medium]
final class DeclaredTypeIdentityTest extends TestCase
{
    #[TestWith(["COMMENT ON TYPE integer[] IS 'ids'", 'Type', "COMMENT ON TYPE integer [] IS 'ids'"])]
    #[TestWith(["SECURITY LABEL ON DOMAIN app.money IS 'secret'", 'Domain', "SECURITY LABEL ON DOMAIN \"app\".\"money\" IS 'secret'"])]
    public function testRetainsTheDeclaredType(string $sql, string $kind, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertTrue($statement instanceof Statement\CommentOnStatement || $statement instanceof Statement\SecurityLabelStatement);
        self::assertInstanceOf(Catalog\DeclaredTypeIdentity::class, $statement->object);
        self::assertSame($kind, $statement->object->kind->name);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsATypeFromAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new Catalog\DeclaredTypeIdentity(Kind\TypeKind::Type, TypeDescriptor::builtin(Dialect::MySql, 'integer'));
    }
}
