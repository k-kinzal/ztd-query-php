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

#[CoversClass(Catalog\CastIdentity::class)]
#[Medium]
final class CastIdentityTest extends TestCase
{
    public function testRetainsTheSourceAndTargetTypes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COMMENT ON CAST (integer AS text) IS 'widening'");
        self::assertInstanceOf(Statement\CommentOnStatement::class, $statement);
        self::assertInstanceOf(Catalog\CastIdentity::class, $statement->object);
        self::assertSame('integer', $statement->object->source->name);
        self::assertSame('text', $statement->object->target->name);
        self::assertSame("COMMENT ON CAST(integer AS text) IS 'widening'", $statement->toString());
    }

    public function testRejectsATypeFromAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new Catalog\CastIdentity(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), TypeDescriptor::builtin(Dialect::MySql, 'integer'));
    }
}
