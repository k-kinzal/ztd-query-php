<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Model\ProductionPattern;
use SqlFaker\Grammar\Walk\GenerationException;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Production;
use SqlFaker\MySql\Grammar\ProductionRule;
use SqlFaker\MySql\Grammar\Terminal;
use SqlFaker\MySql\Grammar\TerminationAnalyzer;
use SqlFaker\MySql\Grammar\ViableAlternatives;

#[CoversClass(ViableAlternatives::class)]
#[UsesClass(GenerationException::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(TerminationAnalyzer::class)]
final class ViableAlternativesTest extends TestCase
{
    /**
     * @return ProductionRule The rule under test: a word, nothing at all, and a walk that never ends
     */
    public static function providerRule(): ProductionRule
    {
        return new ProductionRule('stmt', [
            new Production([new Terminal('SELECT')]),
            new Production([]),
            new Production([new NonTerminal('endless')]),
        ]);
    }

    /**
     * @return ViableAlternatives Narrowing over a grammar holding that rule and an endless one
     */
    public static function providerNarrowing(): ViableAlternatives
    {
        return new ViableAlternatives(new TerminationAnalyzer(new Grammar('stmt', [
            'stmt' => self::providerRule(),
            'endless' => new ProductionRule('endless', [new Production([new NonTerminal('endless')])]),
        ])));
    }

    /**
     * @param list<Production> $productions Alternatives to name
     *
     * @return list<string> Each alternative as the symbols it spells, joined
     */
    public static function providerSpellings(array $productions): array
    {
        return array_map(
            static fn (Production $production): string => implode(
                ' ',
                array_map(static fn ($symbol): string => $symbol->value(), $production->symbols),
            ),
            $productions,
        );
    }

    public function testOf(): void
    {
        self::assertSame(
            ['SELECT', ''],
            self::providerSpellings(self::providerNarrowing()->of(self::providerRule(), null, false)),
        );
    }

    public function testOfOpensOnlyWhatTheAskedForShapeMatches(): void
    {
        self::assertSame(
            ['SELECT'],
            self::providerSpellings(self::providerNarrowing()->of(
                self::providerRule(),
                ProductionPattern::exactly('SELECT'),
                false,
            )),
        );
    }

    public function testOfClosesAnAlternativeProducingNothingWhenOutputWasAskedFor(): void
    {
        self::assertSame(
            ['SELECT'],
            self::providerSpellings(self::providerNarrowing()->of(self::providerRule(), null, true)),
        );
    }

    public function testOfReportsARuleOfferingNothing(): void
    {
        $this->expectException(GenerationException::class);

        self::providerNarrowing()->of(new ProductionRule('stmt', []), null, false);
    }

    public function testOfReportsAShapeNoAlternativeMatches(): void
    {
        $this->expectException(GenerationException::class);

        self::providerNarrowing()->of(self::providerRule(), ProductionPattern::exactly('UPDATE'), false);
    }

    public function testOfReportsARuleWhoseAlternativesAllRunForever(): void
    {
        $this->expectException(GenerationException::class);

        self::providerNarrowing()->of(
            new ProductionRule('endless', [new Production([new NonTerminal('endless')])]),
            null,
            false,
        );
    }
}
