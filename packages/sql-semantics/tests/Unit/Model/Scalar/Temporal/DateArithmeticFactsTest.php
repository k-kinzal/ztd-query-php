<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Temporal\DateArithmeticFacts;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DateArithmeticFacts::class)]
#[Medium]
final class DateArithmeticFactsTest extends TestCase
{
    public function testParameterDerivesTheRequiredInputFamiliesWithoutResolvingValues(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT DATE_ADD(?, INTERVAL ? SECOND)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $shift = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Temporal\DateShift::class, $shift);
        self::assertSame('datetime', $shift->value->type->name);
        self::assertSame('numeric', $shift->quantity->type->name);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\Parameter::class, $shift->quantity);
        self::assertSame('?', $shift->quantity->name);
    }

    #[DataProvider('providerResults')]
    public function testResultUsesDeclaredTemporalInputsAndTheSelectedRelease(string $version, string $expression, string $type): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (d DATE, tm TIME, dt DATETIME, ts TIMESTAMP)');
        $query = (new Binder($schema))->bind('SELECT ' . $expression . ' FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame($type, $query->outputs[0]->expression->type->name);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerResults(): iterable
    {
        yield 'date calendar' => ['mysql-8.4.7', 'd + INTERVAL 1 YEAR_MONTH', 'date'];
        yield 'date clock' => ['mysql-8.4.7', 'd + INTERVAL 1 HOUR', 'datetime'];
        yield 'time clock' => ['mysql-8.4.7', 'tm + INTERVAL 1 SECOND', 'time'];
        yield 'time calendar' => ['mysql-8.4.7', 'tm + INTERVAL 1 DAY', 'datetime'];
        yield 'legacy time calendar' => ['mysql-5.7.44', 'tm + INTERVAL 1 DAY', 'time'];
        yield 'oldest time calendar' => ['mysql-5.6.51', 'tm + INTERVAL 1 DAY', 'time'];
        yield 'datetime' => ['mysql-8.4.7', 'dt - INTERVAL 1 DAY', 'datetime'];
        yield 'timestamp' => ['mysql-8.4.7', 'ts + INTERVAL 1 DAY', 'datetime'];
        yield 'text input is not evaluated' => ['mysql-8.4.7', "'2024-01-01' + INTERVAL 1 DAY", 'varchar'];
        yield 'modern parameter' => ['mysql-8.0.44', '? + INTERVAL 1 DAY', 'date'];
        yield 'legacy parameter' => ['mysql-5.7.44', '? + INTERVAL 1 DAY', 'varchar'];
    }

    public function testNullabilityIncludesConversionAndRangeFailures(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT CURRENT_DATE + INTERVAL 2 DAY, CURRENT_DATE + INTERVAL NULL DAY');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $query->outputs[0]->expression->nullability);
        self::assertSame(\SqlSemantics\Type\Nullability::AlwaysNull, $query->outputs[1]->expression->nullability);
    }
}
