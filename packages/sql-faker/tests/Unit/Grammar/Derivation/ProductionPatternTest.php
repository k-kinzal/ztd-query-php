<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\ProductionPattern;

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

    public function testMatchesAcceptsAnAlternativeContainingEveryNamedSymbol(): void
    {
        self::assertTrue(ProductionPattern::containing('FOREIGN', 'KEY')->matches(['CONSTRAINT', 'FOREIGN', 'KEY']));
    }

    public function testMatchesRejectsAnAlternativeMissingANamedSymbol(): void
    {
        self::assertFalse(ProductionPattern::containing('FOREIGN', 'KEY')->matches(['FOREIGN']));
    }

    public function testMatchesAcceptsOnlyTheAlternativeWrittenExactly(): void
    {
        self::assertTrue(ProductionPattern::exactly('cmdlist', 'ecmd')->matches(['cmdlist', 'ecmd']));
        self::assertFalse(ProductionPattern::exactly('cmdlist', 'ecmd')->matches(['ecmd', 'cmdlist']));
    }

    public function testMatchesRefusesOnlyTheEmptyAlternative(): void
    {
        self::assertTrue(ProductionPattern::nonEmpty()->matches(['ecmd']));
        self::assertFalse(ProductionPattern::nonEmpty()->matches([]));
    }
}
