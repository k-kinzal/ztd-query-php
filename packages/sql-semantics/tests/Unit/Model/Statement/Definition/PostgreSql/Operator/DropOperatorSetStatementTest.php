<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\Kind\OperatorSetKind;
use SqlSemantics\Model\Definition\Catalog\OperatorSetIdentity;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\DropOperatorSetStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropOperatorSetStatement::class)]
#[Medium]
final class DropOperatorSetStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DROP OPERATOR CLASS app.int_ops USING gist RESTRICT');
        self::assertInstanceOf(DropOperatorSetStatement::class, $statement);
        self::assertSame(OperatorSetKind::OperatorClass, $statement->object->kind);
        self::assertSame(['app', 'int_ops'], $statement->object->name->parts);
        self::assertSame('DROP OPERATOR CLASS "app"."int_ops" USING "gist" RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OPERATOR CLASS c USING gist');
        self::assertInstanceOf(DropOperatorSetStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new DropOperatorSetStatement(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql), $statement->object);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OPERATOR CLASS c USING gist');
        self::assertInstanceOf(DropOperatorSetStatement::class, $statement);
        self::assertSame('DROP OPERATOR CLASS "c" USING "gist"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithObjectReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OPERATOR CLASS c USING gist');
        self::assertInstanceOf(DropOperatorSetStatement::class, $statement);
        self::assertSame('DROP OPERATOR FAMILY "f" USING "btree"', $statement->withObject(new OperatorSetIdentity(OperatorSetKind::OperatorFamily, new QualifiedName(['f']), 'btree'))->toString());
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OPERATOR CLASS c USING gist');
        self::assertInstanceOf(DropOperatorSetStatement::class, $statement);
        self::assertSame('DROP OPERATOR CLASS IF EXISTS "c" USING "gist"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withIfExists(true)));
    }

    public function testWithBehaviorReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OPERATOR CLASS c USING gist');
        self::assertInstanceOf(DropOperatorSetStatement::class, $statement);
        self::assertSame(DropBehavior::Cascade, $statement->withBehavior(DropBehavior::Cascade)->behavior);
        self::assertSame(DropBehavior::Default, $statement->behavior);
    }
}
