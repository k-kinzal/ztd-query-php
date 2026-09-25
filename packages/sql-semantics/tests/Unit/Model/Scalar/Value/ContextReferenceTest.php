<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Value\ContextReference;
use SqlSemantics\Model\Scalar\Value\ContextValueKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(ContextReference::class)]
#[Medium]
final class ContextReferenceTest extends TestCase
{
    #[TestWith(['SELECT CURRENT_TIME(3)', ContextValueKind::CurrentTime, 3, 'timetz'])]
    #[TestWith(['SELECT CURRENT_TIMESTAMP', ContextValueKind::CurrentTimestamp, null, 'timestamptz'])]
    #[TestWith(['SELECT CURRENT_USER', ContextValueKind::CurrentUser, null, 'text'])]
    public function testInputsHasNoOperandsForASessionRequest(string $sql, ContextValueKind $request, ?int $precision, string $type): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $value = $statement->outputs[0]->expression;
        self::assertInstanceOf(ContextReference::class, $value);
        self::assertSame($request, $value->request);
        self::assertSame($precision, $value->precision);
        self::assertSame([], $value->inputs());
        self::assertSame($type, $value->type->name);
        self::assertSame(Nullability::NotNull, $value->nullability);
        self::assertSame($sql, $statement->toString());
        self::assertSame($sql, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($sql)));
    }

    #[TestWith([ContextValueKind::SessionUser, null, 'SESSION_USER'])]
    #[TestWith([ContextValueKind::LocalTimestamp, 2, 'LOCALTIMESTAMP(2)'])]
    public function testSpellingReturnsTheRequestName(ContextValueKind $request, ?int $precision, string $serialized): void
    {
        $origin = Expression::literal(1, Dialect::PostgreSql);
        $value = new ContextReference($origin->facts, $origin->source, $request, $precision);
        self::assertSame($request->value, $value->spelling());
        self::assertSame($serialized, $value->structure()->toString());
    }

    #[TestWith([ContextValueKind::CurrentUser, 3])]
    #[TestWith([ContextValueKind::CurrentDate, 0])]
    #[TestWith([ContextValueKind::CurrentTime, -1])]
    public function testRejectsPrecisionOutsideNonnegativeClockValues(ContextValueKind $request, int $precision): void
    {
        $origin = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new ContextReference($origin->facts, $origin->source, $request, $precision);
    }

    public function testWithFactsKeepsTheRequestAndPrecision(): void
    {
        $origin = Expression::literal(1, Dialect::PostgreSql);
        $value = new ContextReference($origin->facts, $origin->source, ContextValueKind::LocalTime, 6);
        $copy = $value->withFacts(new ExpressionFacts($value->type, Nullability::MaybeNull));
        self::assertNotSame($value, $copy);
        self::assertSame(ContextValueKind::LocalTime, $copy->request);
        self::assertSame(6, $copy->precision);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $value->nullability);
    }

    #[TestWith([ContextValueKind::UtcDate])]
    #[TestWith([ContextValueKind::StatementTime])]
    public function testRejectsMySqlClockValuesInAnotherDialect(ContextValueKind $request): void
    {
        $origin = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new ContextReference($origin->facts, $origin->source, $request, null);
    }
}
