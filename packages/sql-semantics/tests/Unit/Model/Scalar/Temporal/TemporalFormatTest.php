<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Temporal\TemporalFormat;
use SqlSemantics\Model\Scalar\Temporal\TemporalFormatKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(TemporalFormat::class)]
#[Medium]
final class TemporalFormatTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindsTheKindAndStandardOnEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("SELECT GET_FORMAT(DATE, 'EUR')");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $format = $statement->outputs[0]->expression;
        self::assertInstanceOf(TemporalFormat::class, $format);
        self::assertSame([TemporalFormatKind::Date, "'EUR'", Nullability::MaybeNull], [$format->temporalKind, $format->standard->spelling(), $format->nullability]);
        self::assertSame("SELECT GET_FORMAT(DATE, 'EUR')", $statement->toString());
        self::assertSame("SELECT GET_FORMAT(DATE, 'EUR')", $binder->bind($statement->toString())->toString());
    }

    public function testInputsContainsTheStandard(): void
    {
        $standard = Expression::literal('ISO', Dialect::MySql);
        self::assertSame([$standard], (new TemporalFormat($standard->facts, $standard->source, TemporalFormatKind::Time, $standard))->inputs());
    }

    public function testSpellingIsTheFunctionName(): void
    {
        $standard = Expression::literal('ISO', Dialect::MySql);
        self::assertSame('GET_FORMAT', (new TemporalFormat($standard->facts, $standard->source, TemporalFormatKind::Time, $standard))->spelling());
    }

    public function testWithFactsKeepsTheKindAndStandard(): void
    {
        $standard = Expression::literal('ISO', Dialect::MySql);
        $format = new TemporalFormat($standard->facts, $standard->source, TemporalFormatKind::Datetime, $standard);
        $copy = $format->withFacts(new ExpressionFacts($format->type, Nullability::MaybeNull));
        self::assertSame([TemporalFormatKind::Datetime, $standard, Nullability::MaybeNull], [$copy->temporalKind, $copy->standard, $copy->nullability]);
    }

    public function testRejectsAnotherDialect(): void
    {
        $standard = Expression::literal('ISO', Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new TemporalFormat($standard->facts, $standard->source, TemporalFormatKind::Date, $standard);
    }
}
