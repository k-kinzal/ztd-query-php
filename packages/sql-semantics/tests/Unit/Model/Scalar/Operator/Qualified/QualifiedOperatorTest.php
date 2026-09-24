<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Operator\Qualified;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedOperator;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(QualifiedOperator::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class QualifiedOperatorTest extends TestCase
{
    /**
     * @param list<string> $qualifier
     */
    #[TestWith([[], '<->', 'OPERATOR(<->)'])]
    #[TestWith([['pg_catalog'], '+', 'OPERATOR(pg_catalog.+)'])]
    #[TestWith([['db', 'Geo'], '@@', 'OPERATOR(db.Geo.@@)'])]
    public function testSpellingJoinsTheQualifierAndTheSymbol(array $qualifier, string $symbol, string $expected): void
    {
        $operator = new QualifiedOperator($qualifier, $symbol);
        self::assertSame($qualifier, $operator->qualifier);
        self::assertSame($symbol, $operator->symbol);
        self::assertSame($expected, $operator->spelling());
    }

    /**
     * @param list<string> $qualifier
     */
    #[TestWith([['a', 'b', 'c'], '+'])]
    #[TestWith([[''], '+'])]
    #[TestWith([['geo'], ''])]
    #[TestWith([['geo'], 'distance'])]
    #[TestWith([['geo'], '+--'])]
    #[TestWith([['geo'], '/*'])]
    public function testSpellingIsUnavailableForAnImpossibleName(array $qualifier, string $symbol): void
    {
        $this->expectException(InvalidStructure::class);
        new QualifiedOperator($qualifier, $symbol);
    }
}
