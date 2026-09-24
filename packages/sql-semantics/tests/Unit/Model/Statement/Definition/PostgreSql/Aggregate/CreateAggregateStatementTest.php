<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Aggregate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\AggregateInputMode;
use SqlSemantics\Model\Definition\Routine\AggregateParameter;
use SqlSemantics\Model\Definition\Routine\OrdinaryAggregate;
use SqlSemantics\Model\Definition\Routine\ZeroArgumentAggregate;
use SqlSemantics\Model\Definition\TypeSystem\Definition\AggregateAttribute;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Aggregate\CreateAggregateStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CreateAggregateStatement::class)]
#[Medium]
final class CreateAggregateStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("CREATE AGGREGATE pct(float8 ORDER BY float8) (sfunc = ordered_set_transition, stype = internal, finalfunc = pct_final, finalfunc_extra, hypothetical = true, parallel = restricted, initcond = '{}')");
        self::assertInstanceOf(CreateAggregateStatement::class, $statement);
        self::assertSame(AggregateAttribute::Hypothetical, $statement->options[4]->attribute);
        self::assertSame('{}', $statement->options[6]->value);
        self::assertFalse($statement->orReplace);
        self::assertSame(StatementKind::Create, $statement->kind);
        self::assertSame("CREATE AGGREGATE \"pct\"(double precision ORDER BY double precision)(SFUNC = \"ordered_set_transition\", STYPE = \"internal\", FINALFUNC = \"pct_final\", FINALFUNC_EXTRA = TRUE, HYPOTHETICAL = TRUE, PARALLEL = 'restricted', INITCOND = '{}')", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testMovingRequiresTheInverseFunctionWithMstype(): void
    {
        $this->expectException(InvalidStructure::class);
        CreateAggregateStatement::moving([new DefinitionOption(AggregateAttribute::Mstype, TypeDescriptor::builtin(Dialect::PostgreSql, 'integer')), new DefinitionOption(AggregateAttribute::Msfunc, new QualifiedName(['f']))]);
    }

    public function testMovingIgnoresInertAttributesWithoutMstype(): void
    {
        CreateAggregateStatement::moving([new DefinitionOption(AggregateAttribute::MfinalfuncExtra, false), new DefinitionOption(AggregateAttribute::Msspace, 0)]);
        $this->expectException(InvalidStructure::class);
        CreateAggregateStatement::moving([new DefinitionOption(AggregateAttribute::Msspace, 4)]);
    }

    public function testRejectsAHypotheticalOrdinaryAggregate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE AGGREGATE a(integer) (sfunc = f, stype = integer)');
        self::assertInstanceOf(CreateAggregateStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([...$statement->options, new DefinitionOption(AggregateAttribute::Hypothetical, true)]);
    }

    public function testRejectsASetArgument(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE AGGREGATE a(integer) (sfunc = f, stype = integer)');
        self::assertInstanceOf(CreateAggregateStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withAggregate(new OrdinaryAggregate(new QualifiedName(['a']), [new AggregateParameter(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), AggregateInputMode::Implicit, null, true)]));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE AGGREGATE a(integer) (sfunc = f, stype = integer)');
        self::assertInstanceOf(CreateAggregateStatement::class, $statement);
        self::assertSame('CREATE AGGREGATE "a"(integer)(SFUNC = "f", STYPE = integer)', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithAggregateReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE AGGREGATE a(integer) (sfunc = f, stype = integer)');
        self::assertInstanceOf(CreateAggregateStatement::class, $statement);
        self::assertSame('CREATE AGGREGATE "s"."b"(*)(SFUNC = "f", STYPE = integer)', $statement->withAggregate(new ZeroArgumentAggregate(new QualifiedName(['s', 'b'])))->toString());
    }

    public function testWithOptionsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE AGGREGATE a(integer) (sfunc = f, stype = integer)');
        self::assertInstanceOf(CreateAggregateStatement::class, $statement);
        self::assertCount(3, $statement->withOptions([...$statement->options, new DefinitionOption(AggregateAttribute::Sortop, new QualifiedName(['<']))])->options);
        self::assertCount(2, $statement->options);
    }

    public function testWithOrReplaceReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE AGGREGATE a(integer) (sfunc = f, stype = integer)');
        self::assertInstanceOf(CreateAggregateStatement::class, $statement);
        self::assertSame('CREATE OR REPLACE AGGREGATE "a"(integer)(SFUNC = "f", STYPE = integer)', $statement->withOrReplace(true)->toString());
    }
}
