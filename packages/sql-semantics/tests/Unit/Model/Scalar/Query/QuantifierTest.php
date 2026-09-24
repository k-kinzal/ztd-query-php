<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Query\QuantifiedComparison;
use SqlSemantics\Model\Scalar\Query\Quantifier;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Quantifier::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class QuantifierTest extends TestCase
{
    #[TestWith(['ALL', Quantifier::All])]
    #[TestWith(['ANY', Quantifier::Any])]
    #[TestWith(['SOME', Quantifier::Some])]
    public function testBindsEachQuantifierKeyword(string $keyword, Quantifier $quantifier): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 = ' . $keyword . ' (SELECT 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $comparison = $statement->outputs[0]->expression;
        self::assertInstanceOf(QuantifiedComparison::class, $comparison);
        self::assertSame($quantifier, $comparison->quantifier);
        self::assertSame($keyword, $quantifier->value);
        self::assertStringContainsString($keyword, $statement->toString());
    }

    public function testSpellsEachQuantifierAsItsKeyword(): void
    {
        self::assertSame(['ALL', 'ANY', 'SOME'], array_map(static fn (Quantifier $quantifier): string => $quantifier->value, Quantifier::cases()));
    }
}
