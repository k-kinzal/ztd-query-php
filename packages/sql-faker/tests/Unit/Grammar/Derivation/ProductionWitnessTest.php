<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Derivation;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Choice\ByteChoices;
use SqlFaker\Grammar\Derivation\CompletionCosts;
use SqlFaker\Grammar\Derivation\Derivation;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\ProductionWitness;
use SqlFaker\Grammar\Derivation\TerminationAnalyzer;
use SqlFaker\Grammar\Derivation\WitnessNode;
use SqlFaker\Grammar\Terminal;
use Tests\Fixtures\SqlFaker\CoverageFixture;

#[CoversClass(ProductionWitness::class)]
#[UsesClass(WitnessNode::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ByteChoices::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(TerminationAnalyzer::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\DerivationNode::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationCost::class)]
#[UsesClass(\SqlFaker\Grammar\Grammar::class)]
#[UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[UsesClass(\SqlFaker\Grammar\Production::class)]
#[UsesClass(\SqlFaker\Grammar\ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\PlanBuilder::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ProductionPattern::class)]
final class ProductionWitnessTest extends TestCase
{
    /**
     * @return list<array{string, int, list<string>}>
     */
    public static function providerAlternatives(): array
    {
        return [
            ['stmt', 0, ['SELECT', '1']],
            ['stmt', 1, ['DELETE', 'FROM', 'missing']],
            ['expr', 0, ['SELECT', '1']],
            ['expr', 1, ['SELECT', '1', '+', '1']],
            ['tail', 0, ['SELECT', '1']],
            ['tail', 1, ['SELECT', '1', 'AS', 'name']],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerAlternatives')]
    public function testEncodeReproducesEveryFeasibleProductionWithAllSiblings(string $rule, int $ordinal, array $expected): void
    {
        $grammar = CoverageFixture::syntaxGrammar();
        $supported = static fn (string $token): bool => true;
        $search = new ProductionWitness($grammar, $supported);
        $witness = $search->find('stmt', $rule, $ordinal);
        self::assertNotNull($witness);
        self::assertArrayHasKey($rule . "\0" . $ordinal, $witness->contains);
        $costs = new CompletionCosts($grammar, $supported);
        $input = $search->encode($witness);
        $lexical = $this->createMock(\SqlFaker\Grammar\LexicalGrammar::class);
        $lexical->method('supports')->willReturn(true);
        $lexical->method('spellings')->willReturnCallback(static fn (string $terminal): array => [$terminal === '@TRIVIA' ? ' ' : $terminal]);
        $builder = new \SqlFaker\Grammar\Derivation\PlanBuilder($grammar, $lexical);
        $plan = GenerationPlan::fromBytes($input, $builder, GenerationPlan::all()->requiringNonEmpty());
        self::assertSame($witness->cost, $plan->expansionBudget());
        self::assertSame($input, $search->encode($witness));
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer($grammar, $supported), $costs);
        self::assertSame($expected, array_map(static fn (Terminal $token): string => $token->value, $derivation->of('stmt', $plan)));
    }

    public function testFindKeepsUnsupportedAndOutsideRootAlternativesUnreachable(): void
    {
        $search = new ProductionWitness(CoverageFixture::grammar(), static fn (string $token): bool => $token !== '1');
        self::assertNull($search->find('stmt', 'expr', 0));
        self::assertNull($search->find('stmt', 'outside', 0));
        self::assertNull($search->find('stmt', 'missing', 0));
        $witness = $search->find('stmt', 'expr', 2);
        self::assertNotNull($witness);
        self::assertSame(2, $witness->cost);
        self::assertSame([['stmt', 0], ['expr', 2]], $witness->sequence());
    }
    public function testSettledRetainsTheCheapestWitnessForEachTargetAndEmptinessState(): void
    {
        $grammar = CoverageFixture::syntaxGrammar();
        $search = new ProductionWitness($grammar, static fn (string $token): bool => true);
        $best = $search->settled('expr', 1, []);
        self::assertSame(1, $best['stmt'][1]->cost);
        self::assertSame(5, $best['stmt'][3]->cost);
        self::assertSame([['stmt', 0], ['expr', 1], ['expr', 0], ['expr', 0], ['tail', 0]], $best['stmt'][3]->sequence());
        self::assertSame($best, $search->settled('expr', 1, $best));
    }

    public function testSequencesCombinesSiblingsWithoutDroppingTargetOrNonEmptyStates(): void
    {
        $grammar = CoverageFixture::syntaxGrammar();
        $search = new ProductionWitness($grammar, static fn (string $token): bool => true);
        $best = $search->settled('expr', 1, []);
        $states = $search->sequences($grammar->ruleMap['stmt']->alternatives[0], $best, 0);
        self::assertSame(2, $states[1][0]);
        self::assertSame(4, $states[3][0]);
        self::assertSame([$best['expr'][3], $best['tail'][0]], $states[3][1]);
        self::assertSame([], $search->sequences($grammar->ruleMap['expr']->alternatives[1], [], 0));
        self::assertSame([2 => [0, []]], $search->sequences($grammar->ruleMap['tail']->alternatives[0], [], 2));
    }

}
