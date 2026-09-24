<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\Aggregates;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\OrdinaryAggregate;
use SqlSemantics\Model\Definition\Routine\ZeroArgumentAggregate;
use SqlSemantics\Model\Definition\TypeSystem\Definition\AggregateAttribute;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Aggregate\CreateAggregateStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Aggregates::class)]
#[Medium]
final class AggregatesTest extends TestCase
{
    public function testCreateKeepsTheLastArgumentAndIgnoresUnknownAttributes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE AGGREGATE a(integer) (sfunc = f, stype = integer, foo = 1, "SFUNC" = x, sfunc = g)');
        self::assertInstanceOf(CreateAggregateStatement::class, $statement);
        self::assertSame([AggregateAttribute::Stype, AggregateAttribute::Sfunc], array_map(static fn ($option) => $option->attribute, $statement->options));
    }

    #[TestWith(['CREATE AGGREGATE a(integer) (sfunc = f)', 'definition-requirement'])]
    #[TestWith(['CREATE AGGREGATE a(integer) (sfunc = f, stype = integer, basetype = integer)', 'definition-attribute'])]
    #[TestWith(['CREATE AGGREGATE a(integer) (sfunc = f, stype = integer, sspace = 1.5)', 'definition-argument'])]
    #[TestWith(["CREATE AGGREGATE a(integer) (sfunc = f, stype = integer, parallel = 'SAFE')", 'definition-argument'])]
    #[TestWith(['CREATE AGGREGATE a(integer) (sfunc = f, stype = integer, mstype = integer)', 'definition-requirement'])]
    #[TestWith(['CREATE AGGREGATE a(SETOF integer) (sfunc = f, stype = integer)', 'set-of-declaration'])]
    #[TestWith(['CREATE AGGREGATE a (sfunc = f, stype = integer)', 'definition-requirement'])]
    public function testCreateDiagnosesImpossibleDefinitions(string $sql, string $violation): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::from($violation)->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testLegacyReadsTheBaseTypeAsTheSignature(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $any = $binder->bind('CREATE AGGREGATE a (basetype = "any", sfunc = f, stype = bigint)');
        self::assertInstanceOf(CreateAggregateStatement::class, $any);
        self::assertInstanceOf(ZeroArgumentAggregate::class, $any->aggregate);
        $typed = $binder->bind('CREATE AGGREGATE s.a (sfunc1 = f, basetype = SETOF text, stype1 = text)');
        self::assertInstanceOf(CreateAggregateStatement::class, $typed);
        self::assertInstanceOf(OrdinaryAggregate::class, $typed->aggregate);
        self::assertSame('CREATE AGGREGATE "s"."a"(text)(SFUNC = "f", STYPE = text)', $typed->toString());
    }
}
