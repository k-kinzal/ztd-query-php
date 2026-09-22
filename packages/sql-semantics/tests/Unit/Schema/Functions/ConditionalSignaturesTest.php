<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Functions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Function\AggregateCall;
use SqlSemantics\Model\Scalar\Function\DeclaredFunction;
use SqlSemantics\Model\Scalar\Function\FunctionCall;
use SqlSemantics\Schema\Functions\ConditionalSignatures;
use SqlSemantics\Schema\FunctionSignature;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ConditionalSignatures::class)]
final class ConditionalSignaturesTest extends TestCase
{
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testForDialectRegistersOrdinaryConditionalFunctions(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build();
        $statement = (new Binder($schema))->bind('SELECT COALESCE(1,2),NULLIF(1,2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $coalesce = $statement->outputs[0]->expression;
        $nullIf = $statement->outputs[1]->expression;
        self::assertInstanceOf(FunctionCall::class, $coalesce);
        self::assertInstanceOf(FunctionCall::class, $nullIf);
        self::assertInstanceOf(DeclaredFunction::class, $coalesce->function);
        self::assertInstanceOf(DeclaredFunction::class, $nullIf->function);
        self::assertContains($coalesce->function->signature, $schema->functions);
        self::assertContains($nullIf->function->signature, $schema->functions);
        self::assertSame(Nullability::NotNull, $coalesce->nullability);
        self::assertSame(Nullability::MaybeNull, $nullIf->nullability);
    }

    public function testForDialectDistinguishesSqliteScalarAndAggregateExtrema(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(n INTEGER)'));
        $statement = $binder->bind('SELECT min(n), max(n), min(n,2), max(n,2) FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(AggregateCall::class, $statement->outputs[0]->expression);
        self::assertInstanceOf(AggregateCall::class, $statement->outputs[1]->expression);
        self::assertInstanceOf(FunctionCall::class, $statement->outputs[2]->expression);
        self::assertInstanceOf(FunctionCall::class, $statement->outputs[3]->expression);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    #[TestWith(['coalesce'])]
    #[TestWith(['nullif'])]
    public function testForDialectAllowsApplicationOverrides(string $name): void
    {
        $text = new TypeDescriptor(Dialect::Sqlite, BuiltinIdentity::Text);
        $integer = new TypeDescriptor(Dialect::Sqlite, BuiltinIdentity::Integer);
        $signature = new FunctionSignature($name, [$integer,$integer], $text, Nullability::AlwaysNull);
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build()->withFunctions($signature);
        $binder = new Binder($schema);
        $statement = $binder->bind('SELECT '.$name.'(1,2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $value = $statement->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $value);
        self::assertInstanceOf(DeclaredFunction::class, $value->function);
        self::assertSame($signature, $value->function->signature);
        self::assertSame($text, $value->type);
        self::assertSame(Nullability::AlwaysNull, $value->nullability);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    #[TestWith(['SELECT coalesce()'])]
    #[TestWith(['SELECT coalesce(1)'])]
    #[TestWith(['SELECT nullif(1)'])]
    #[TestWith(['SELECT min()'])]
    public function testForDialectRejectsInvalidRegisteredArity(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage('declared number of arguments');
        (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind($sql, strict: false);
    }

    #[TestWith([Nullability::AlwaysNull, Nullability::NotNull])]
    #[TestWith([Nullability::NotNull, Nullability::NotNull])]
    #[TestWith([Nullability::MaybeNull, Nullability::NotNull])]
    public function testCoalesceUsesArgumentFactsWithoutFetchingValues(Nullability $left, Nullability $expected): void
    {
        self::assertSame($expected, ConditionalSignatures::coalesce([$left,Nullability::NotNull]));
    }

    #[TestWith([Nullability::AlwaysNull, Nullability::AlwaysNull])]
    #[TestWith([Nullability::NotNull, Nullability::MaybeNull])]
    public function testNullIfRetainsTheFirstArgumentsNullPossibility(Nullability $left, Nullability $expected): void
    {
        self::assertSame($expected, ConditionalSignatures::nullIf([$left,Nullability::NotNull]));
    }
}
