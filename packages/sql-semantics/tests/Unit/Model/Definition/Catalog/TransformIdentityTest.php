<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(Catalog\TransformIdentity::class)]
#[Medium]
final class TransformIdentityTest extends TestCase
{
    public function testRetainsTheTypeAndLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COMMENT ON TRANSFORM FOR hstore LANGUAGE plpython3u IS 'x'");
        self::assertInstanceOf(Statement\CommentOnStatement::class, $statement);
        self::assertInstanceOf(Catalog\TransformIdentity::class, $statement->object);
        self::assertSame('hstore', $statement->object->type->name);
        self::assertSame('plpython3u', $statement->object->language);
        self::assertSame("COMMENT ON TRANSFORM FOR \"hstore\" LANGUAGE \"plpython3u\" IS 'x'", $statement->toString());
    }

    public function testRejectsAnEmptyLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new Catalog\TransformIdentity(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), '');
    }

    public function testRejectsATypeFromAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new Catalog\TransformIdentity(TypeDescriptor::builtin(Dialect::Sqlite, 'integer'), 'plpgsql');
    }
}
