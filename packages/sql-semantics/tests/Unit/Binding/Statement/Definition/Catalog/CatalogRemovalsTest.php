<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Catalog\CatalogRemovals;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CatalogRemovals::class)]
#[Medium]
final class CatalogRemovalsTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['DROP SEQUENCE s1, s2', \SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\DropRelationsStatement::class, 'DROP SEQUENCE "s1", "s2"'])]
    #[TestWith(['DROP FOREIGN TABLE IF EXISTS f CASCADE', \SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\DropRelationsStatement::class, 'DROP FOREIGN TABLE IF EXISTS "f" CASCADE'])]
    #[TestWith(['DROP CONVERSION c', \SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\DropSchemaObjectsStatement::class, 'DROP CONVERSION "c"'])]
    #[TestWith(['DROP ACCESS METHOD am', \SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\DropNamedObjectsStatement::class, 'DROP ACCESS METHOD "am"'])]
    #[TestWith(['DROP PUBLICATION p', \SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\DropNamedObjectsStatement::class, 'DROP PUBLICATION "p"'])]
    #[TestWith(['DROP POLICY IF EXISTS p ON t', \SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\DropRelationMemberStatement::class, 'DROP POLICY IF EXISTS "p" ON "t"'])]
    #[TestWith(['DROP RULE r ON t RESTRICT', \SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\DropRelationMemberStatement::class, 'DROP RULE "r" ON "t" RESTRICT'])]
    #[TestWith(['DROP TYPE SETOF json, integer[]', \SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\DropTypesStatement::class, 'DROP TYPE json, integer []'])]
    #[TestWith(['DROP DOMAIN d', \SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\DropTypesStatement::class, 'DROP DOMAIN "d"'])]
    public function testBindBindsEachRemovalClass(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    #[TestWith(['DROP COLLATION a.b.c'])]
    public function testBindRejectsAnOverQualifiedName(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CatalogObjectName->message());
        $binder->bind($sql, strict: false);
    }
}
