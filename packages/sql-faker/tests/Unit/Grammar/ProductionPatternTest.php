<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\ProductionPattern;

#[CoversClass(ProductionPattern::class)]
final class ProductionPatternTest extends TestCase
{
    public function testContainingRequiresEveryNamedSymbol(): void
    {
        $pattern = ProductionPattern::containing('FOREIGN', 'KEY');

        self::assertTrue($pattern->matches(['CONSTRAINT', 'FOREIGN', 'KEY', 'REFERENCES']));
        self::assertFalse($pattern->matches(['CONSTRAINT', 'FOREIGN', 'REFERENCES']));
    }

    public function testExactlyMatchesOrderAndCardinality(): void
    {
        $pattern = ProductionPattern::exactly(first: 'CONSTRAINT', second: 'name');

        self::assertTrue($pattern->matches(['CONSTRAINT', 'name']));
        self::assertFalse($pattern->matches(['name', 'CONSTRAINT']));
        self::assertFalse($pattern->matches(['CONSTRAINT', 'name', 'FOREIGN']));
    }

    public function testExactlyCanSelectAnEmptyProduction(): void
    {
        $pattern = ProductionPattern::exactly();

        self::assertTrue($pattern->matches([]));
        self::assertFalse($pattern->matches(['COMMA']));
    }

    public function testNonEmptyRejectsOnlyAnEmptyProduction(): void
    {
        $pattern = ProductionPattern::nonEmpty();

        self::assertTrue($pattern->matches(['name']));
        self::assertFalse($pattern->matches([]));
    }
}
