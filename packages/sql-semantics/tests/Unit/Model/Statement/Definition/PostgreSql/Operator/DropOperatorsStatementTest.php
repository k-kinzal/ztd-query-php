<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\OperatorIdentity;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\DropOperatorsStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(DropOperatorsStatement::class)]
#[Medium]
final class DropOperatorsStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DROP OPERATOR app.+ (integer, text), - (NONE, bigint) RESTRICT');
        self::assertInstanceOf(DropOperatorsStatement::class, $statement);
        self::assertSame(['app', '+'], $statement->operators[0]->name->parts);
        self::assertSame('bigint', $statement->operators[1]->right?->name);
        self::assertSame(StatementKind::Drop, $statement->kind);
        self::assertSame('DROP OPERATOR "app".+ (integer, text), - (NONE, bigint) RESTRICT', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OPERATOR - (NONE, bigint)');
        self::assertInstanceOf(DropOperatorsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new DropOperatorsStatement(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql), $statement->operators);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OPERATOR - (NONE, bigint)');
        self::assertInstanceOf(DropOperatorsStatement::class, $statement);
        self::assertSame('DROP OPERATOR - (NONE, bigint)', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithOperatorsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OPERATOR - (NONE, bigint)');
        self::assertInstanceOf(DropOperatorsStatement::class, $statement);
        $operator = new OperatorIdentity(new QualifiedName(['#']), TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'));
        self::assertSame('DROP OPERATOR # (integer, integer)', $statement->withOperators([$operator])->toString());
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OPERATOR - (NONE, bigint)');
        self::assertInstanceOf(DropOperatorsStatement::class, $statement);
        self::assertSame('DROP OPERATOR IF EXISTS - (NONE, bigint)', $statement->withIfExists(true)->toString());
    }

    public function testWithBehaviorReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OPERATOR - (NONE, bigint)');
        self::assertInstanceOf(DropOperatorsStatement::class, $statement);
        self::assertSame(DropBehavior::Cascade, $statement->withBehavior(DropBehavior::Cascade)->behavior);
        self::assertSame(DropBehavior::Default, $statement->behavior);
    }
}
