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

#[CoversClass(Catalog\NamedIdentity::class)]
#[Medium]
final class NamedIdentityTest extends TestCase
{
    #[TestWith(['COMMENT ON EXTENSION postgis IS NULL', Kind\NamedObjectKind::Extension, 'postgis'])]
    #[TestWith(['COMMENT ON PROCEDURAL LANGUAGE plpgsql IS NULL', Kind\NamedObjectKind::Language, 'plpgsql'])]
    #[TestWith(["COMMENT ON SCHEMA app IS 'x'", Kind\NamedObjectKind::Schema, 'app'])]
    public function testRetainsTheObjectClassAndName(string $sql, Kind\NamedObjectKind $kind, string $name): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(Statement\CommentOnStatement::class, $statement);
        self::assertEquals(new Catalog\NamedIdentity($kind, $name), $statement->object);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new Catalog\NamedIdentity(Kind\NamedObjectKind::Schema, '');
    }
}
