<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\OperatorIdentity;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\AlterOperatorStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(AlterOperatorStatement::class)]
#[Medium]
final class AlterOperatorStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER OPERATOR s.=== (NONE, integer) SET (JOIN, COMMUTATOR = ===, MERGES = on)');
        self::assertInstanceOf(AlterOperatorStatement::class, $statement);
        self::assertNull($statement->operator->left);
        self::assertNull($statement->options[0]->value);
        self::assertTrue($statement->options[2]->value);
        self::assertSame(StatementKind::Alter, $statement->kind);
        self::assertSame('ALTER OPERATOR "s".=== (NONE, integer) SET (JOIN = NONE, COMMUTATOR = ===, MERGES = TRUE)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnUnsetCapability(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR === (integer, integer) SET (HASHES)');
        self::assertInstanceOf(AlterOperatorStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([new DefinitionOption(OperatorAttribute::Merges, null)]);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR === (integer, integer) SET (HASHES)');
        self::assertInstanceOf(AlterOperatorStatement::class, $statement);
        self::assertSame('ALTER OPERATOR === (integer, integer) SET (HASHES = TRUE)', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithOperatorReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR === (integer, integer) SET (HASHES)');
        self::assertInstanceOf(AlterOperatorStatement::class, $statement);
        $operator = new OperatorIdentity(new QualifiedName(['#']), TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), TypeDescriptor::builtin(Dialect::PostgreSql, 'text'));
        self::assertSame('ALTER OPERATOR # (text, text) SET (HASHES = TRUE)', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOperator($operator)));
        self::assertSame(['==='], $statement->operator->name->parts);
    }

    public function testWithOptionsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR === (integer, integer) SET (HASHES)');
        self::assertInstanceOf(AlterOperatorStatement::class, $statement);
        self::assertSame('ALTER OPERATOR === (integer, integer) SET (RESTRICT = "eqsel")', $statement->withOptions([new DefinitionOption(OperatorAttribute::Restrict, new QualifiedName(['eqsel']))])->toString());
    }
}
