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
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(Catalog\OperatorIdentity::class)]
#[Medium]
final class OperatorIdentityTest extends TestCase
{
    #[TestWith(["COMMENT ON OPERATOR app.- (NONE, integer) IS 'negate'", null, 'integer', "COMMENT ON OPERATOR \"app\".- (NONE, integer) IS 'negate'"])]
    #[TestWith(["COMMENT ON OPERATOR + (integer, integer) IS 'add'", 'integer', 'integer', "COMMENT ON OPERATOR + (integer, integer) IS 'add'"])]
    public function testRetainsTheOperandTypesOfEachSide(string $sql, ?string $left, string $right, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(Statement\CommentOnStatement::class, $statement);
        self::assertInstanceOf(Catalog\OperatorIdentity::class, $statement->object);
        self::assertSame($left, $statement->object->left?->name);
        self::assertSame($right, $statement->object->right?->name);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnOperatorWithoutOperandTypes(): void
    {
        $this->expectException(InvalidStructure::class);
        new Catalog\OperatorIdentity(new QualifiedName(['+']), null, null);
    }

    public function testRejectsAnOperandTypeFromAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new Catalog\OperatorIdentity(new QualifiedName(['+']), TypeDescriptor::builtin(Dialect::MySql, 'integer'), null);
    }
}
