<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Cast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\TransformIdentity;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Cast\DropTransformStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(DropTransformStatement::class)]
#[Medium]
final class DropTransformStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DROP TRANSFORM FOR integer [] LANGUAGE plperl RESTRICT');
        self::assertInstanceOf(DropTransformStatement::class, $statement);
        self::assertSame('integer[]', $statement->transform->type->name);
        self::assertSame('plperl', $statement->transform->language);
        self::assertSame('DROP TRANSFORM FOR integer [] LANGUAGE "plperl" RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TRANSFORM FOR hstore LANGUAGE plperl');
        self::assertInstanceOf(DropTransformStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new DropTransformStatement(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::Sqlite), $statement->transform);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TRANSFORM FOR hstore LANGUAGE plperl');
        self::assertInstanceOf(DropTransformStatement::class, $statement);
        self::assertSame('DROP TRANSFORM FOR "hstore" LANGUAGE "plperl"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithTransformReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TRANSFORM FOR hstore LANGUAGE plperl');
        self::assertInstanceOf(DropTransformStatement::class, $statement);
        self::assertSame('DROP TRANSFORM FOR jsonb LANGUAGE "plperl"', $statement->withTransform(new TransformIdentity(TypeDescriptor::builtin(Dialect::PostgreSql, 'jsonb'), 'plperl'))->toString());
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TRANSFORM FOR hstore LANGUAGE plperl');
        self::assertInstanceOf(DropTransformStatement::class, $statement);
        self::assertTrue($statement->withIfExists(true)->ifExists);
        self::assertFalse($statement->ifExists);
    }

    public function testWithBehaviorReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TRANSFORM FOR hstore LANGUAGE plperl');
        self::assertInstanceOf(DropTransformStatement::class, $statement);
        self::assertSame('DROP TRANSFORM FOR "hstore" LANGUAGE "plperl" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withBehavior(DropBehavior::Cascade)));
    }
}
