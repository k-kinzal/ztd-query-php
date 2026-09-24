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
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Catalog\TypeNameIdentity::class)]
#[Medium]
final class TypeNameIdentityTest extends TestCase
{
    #[TestWith(['ALTER TYPE app.mood OWNER TO alice', Kind\TypeKind::Type])]
    #[TestWith(['ALTER DOMAIN app.mood OWNER TO alice', Kind\TypeKind::Domain])]
    public function testRetainsTheCatalogName(string $sql, Kind\TypeKind $kind): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(Statement\ChangeObjectOwnerStatement::class, $statement);
        self::assertEquals(new Catalog\TypeNameIdentity($kind, new QualifiedName(['app', 'mood'])), $statement->object);
    }

    public function testRejectsAnOverQualifiedName(): void
    {
        $this->expectException(InvalidStructure::class);
        new Catalog\TypeNameIdentity(Kind\TypeKind::Type, new QualifiedName(['db', 'app', 'mood']));
    }
}
