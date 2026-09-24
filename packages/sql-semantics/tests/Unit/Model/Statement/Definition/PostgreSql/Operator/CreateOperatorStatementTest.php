<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Operator\CreateOperatorStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateOperatorStatement::class)]
#[Medium]
final class CreateOperatorStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("CREATE OPERATOR <-> (FUNCTION = s.dist, LEFTARG = point, RIGHTARG = 'point', NEGATOR = OPERATOR(s.!<->), RESTRICT = eqsel, HASHES = off)");
        self::assertInstanceOf(CreateOperatorStatement::class, $statement);
        self::assertSame(['<->'], $statement->name->parts);
        self::assertSame(OperatorAttribute::Negator, $statement->options[3]->attribute);
        self::assertFalse($statement->options[5]->value);
        self::assertSame(StatementKind::Create, $statement->kind);
        self::assertSame('CREATE OPERATOR <-> (FUNCTION = "s"."dist", LEFTARG = point, RIGHTARG = "point", NEGATOR = OPERATOR("s".!<->), RESTRICT = "eqsel", HASHES = FALSE)', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsANameThatIsNotAnOperator(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR === (FUNCTION = f, RIGHTARG = integer)');
        self::assertInstanceOf(CreateOperatorStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName(new QualifiedName(['equals']));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR === (FUNCTION = f, RIGHTARG = integer)');
        self::assertInstanceOf(CreateOperatorStatement::class, $statement);
        self::assertSame('CREATE OPERATOR === (FUNCTION = "f", RIGHTARG = integer)', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR === (FUNCTION = f, RIGHTARG = integer)');
        self::assertInstanceOf(CreateOperatorStatement::class, $statement);
        self::assertSame('CREATE OPERATOR "s".## (FUNCTION = "f", RIGHTARG = integer)', $statement->withName(new QualifiedName(['s', '##']))->toString());
        self::assertSame(['==='], $statement->name->parts);
    }

    public function testWithOptionsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR === (FUNCTION = f, RIGHTARG = integer)');
        self::assertInstanceOf(CreateOperatorStatement::class, $statement);
        $options = [...$statement->options, new DefinitionOption(OperatorAttribute::Merges, true)];
        self::assertSame('CREATE OPERATOR === (FUNCTION = "f", RIGHTARG = integer, MERGES = TRUE)', $statement->withOptions($options)->toString());
    }
}
