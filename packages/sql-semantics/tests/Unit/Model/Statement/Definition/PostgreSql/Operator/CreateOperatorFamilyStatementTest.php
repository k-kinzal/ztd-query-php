<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\CreateOperatorFamilyStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateOperatorFamilyStatement::class)]
#[Medium]
final class CreateOperatorFamilyStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE OPERATOR FAMILY app.ints USING "Hash"');
        self::assertInstanceOf(CreateOperatorFamilyStatement::class, $statement);
        self::assertSame('Hash', $statement->method);
        self::assertSame(StatementKind::Create, $statement->kind);
        self::assertSame('CREATE OPERATOR FAMILY "app"."ints" USING "Hash"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnEmptyAccessMethod(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR FAMILY f USING btree');
        self::assertInstanceOf(CreateOperatorFamilyStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withMethod('');
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR FAMILY f USING btree');
        self::assertInstanceOf(CreateOperatorFamilyStatement::class, $statement);
        self::assertSame('CREATE OPERATOR FAMILY "f" USING "btree"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR FAMILY f USING btree');
        self::assertInstanceOf(CreateOperatorFamilyStatement::class, $statement);
        self::assertSame(['s', 'g'], $statement->withName(new QualifiedName(['s', 'g']))->name->parts);
        self::assertSame(['f'], $statement->name->parts);
    }

    public function testWithMethodReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR FAMILY f USING btree');
        self::assertInstanceOf(CreateOperatorFamilyStatement::class, $statement);
        self::assertSame('CREATE OPERATOR FAMILY "f" USING "hash"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withMethod('hash')));
    }
}
