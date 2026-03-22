<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation;

use Faker\Factory;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\Symbol;
use SqlFaker\Contract\TerminationLengths;
use SqlFaker\Generation\FakerRandomSource;
use SqlFaker\Generation\TerminationLengthComputer;
use SqlFaker\Generation\TerminalDeriver;

function exactDerivationLimitGrammar(): Grammar
{
    $rules = [];
    foreach (range(0, 4999) as $index) {
        $rules['n' . $index] = new ProductionRule('n' . $index, [
            new Production([
                $index === 4999 ? new Symbol('DONE', false) : new Symbol('n' . ($index + 1), true),
            ]),
        ]);
    }

    return new Grammar('n0', $rules);
}

#[CoversNothing]
#[Large]
final class TerminalDeriverTest extends TestCase
{
    public function testDeriveUsesDefaultStartRuleAndReturnsTerminalSequence(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Symbol('IDENT', false)]),
            ]),
        ]);

        $deriver = new TerminalDeriver(new FakerRandomSource(Factory::create()), 'stmt');

        self::assertSame(
            ['IDENT'],
            $deriver->derive($grammar, new TerminationLengths(['stmt' => 1]), new GenerationRequest())->terminals,
        );
    }

    public function testDeriveTreatsTargetDepthLessThanOneAsOne(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Symbol('A', false),
                    new Symbol('B', false),
                    new Symbol('C', false),
                    new Symbol('D', false),
                ]),
                new Production([new Symbol('SHORT', false)]),
            ]),
        ]);
        $lengths = (new TerminationLengthComputer())->compute($grammar);
        $deriver = new TerminalDeriver(new FakerRandomSource(Factory::create()), 'stmt');

        self::assertSame(['SHORT'], $deriver->derive($grammar, $lengths, new GenerationRequest(startRule: 'stmt', maxDepth: 0))->terminals);
        self::assertSame(['SHORT'], $deriver->derive($grammar, $lengths, new GenerationRequest(startRule: 'stmt', maxDepth: -10))->terminals);
        self::assertSame(['SHORT'], $deriver->derive($grammar, $lengths, new GenerationRequest(startRule: 'stmt', maxDepth: 1))->terminals);
    }

    public function testDeriveSelectsShortestAlternativeAtTargetDepth(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Symbol('SELECT', false),
                    new Symbol('expr', true),
                    new Symbol('FROM', false),
                    new Symbol('table', true),
                ]),
                new Production([new Symbol('SHORT', false)]),
            ]),
            'expr' => new ProductionRule('expr', [
                new Production([new Symbol('x', false)]),
            ]),
            'table' => new ProductionRule('table', [
                new Production([new Symbol('t', false)]),
            ]),
        ]);
        $lengths = (new TerminationLengthComputer())->compute($grammar);
        $deriver = new TerminalDeriver(new FakerRandomSource(Factory::create()), 'stmt');

        self::assertSame(
            ['SHORT'],
            $deriver->derive($grammar, $lengths, new GenerationRequest(startRule: 'stmt', maxDepth: 1))->terminals,
        );
    }

    public function testDeriveSelectsFirstAlternativeOnLengthTie(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Symbol('FIRST', false)]),
                new Production([new Symbol('SECOND', false)]),
            ]),
        ]);
        $deriver = new TerminalDeriver(new FakerRandomSource(Factory::create()), 'stmt');

        self::assertSame(
            ['FIRST'],
            $deriver->derive($grammar, new TerminationLengths(['stmt' => 1]), new GenerationRequest(startRule: 'stmt', maxDepth: 1))->terminals,
        );
    }

    public function testDeriveConsumesRandomChoicesInLeftmostDerivationOrder(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Symbol('first', true),
                    new Symbol('second', true),
                ]),
            ]),
            'first' => new ProductionRule('first', [
                new Production([new Symbol('A', false)]),
                new Production([new Symbol('B', false)]),
            ]),
            'second' => new ProductionRule('second', [
                new Production([new Symbol('1', false)]),
                new Production([new Symbol('2', false)]),
            ]),
        ]);
        $faker = new class ([0, 1, 0]) extends \Faker\Generator {
            /** @var list<int> */
            private array $numberBetweenValues;

            /**
             * @param list<int> $numberBetweenValues
             */
            public function __construct(array $numberBetweenValues)
            {
                parent::__construct();
                $this->numberBetweenValues = $numberBetweenValues;
            }

            /**
             * @param mixed $int1
             * @param mixed $int2
             */
            #[\Override]
            public function numberBetween($int1 = 0, $int2 = 2147483647): int
            {
                $next = array_shift($this->numberBetweenValues);
                $lower = is_int($int1) ? $int1 : 0;
                $upper = is_int($int2) ? $int2 : 2147483647;
                $value = is_int($next) ? $next : min($lower, $upper);
                $min = min($lower, $upper);
                $max = max($lower, $upper);

                return max($min, min($max, $value));
            }
        };
        $deriver = new TerminalDeriver(new FakerRandomSource($faker), 'stmt');

        self::assertSame(
            ['B', '1'],
            $deriver->derive($grammar, new TerminationLengths(['stmt' => 2, 'first' => 1, 'second' => 1]), new GenerationRequest(startRule: 'stmt'))->terminals,
        );
    }

    #[DataProvider('providerRandomAlternativeSeeds')]
    public function testDeriveSelectsRandomAlternativeBeforeTargetDepth(int $seed1, int $seed2): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Symbol('A', false)]),
                new Production([new Symbol('B', false)]),
                new Production([new Symbol('C', false)]),
            ]),
        ]);

        $deriver1 = new TerminalDeriver(new FakerRandomSource(Factory::create()), 'stmt');
        $deriver2 = new TerminalDeriver(new FakerRandomSource(Factory::create()), 'stmt');

        $result1 = $deriver1->derive($grammar, new TerminationLengths(['stmt' => 1]), new GenerationRequest(startRule: 'stmt', seed: $seed1))->terminals;
        $result2 = $deriver2->derive($grammar, new TerminationLengths(['stmt' => 1]), new GenerationRequest(startRule: 'stmt', seed: $seed2))->terminals;

        self::assertNotSame($result1, $result2);
    }

    public function testDeriveSwitchesToShortestSelectionAtExactlyTargetDepth(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Symbol('inner', true)]),
            ]),
            'inner' => new ProductionRule('inner', [
                new Production([new Symbol('choice', true)]),
            ]),
            'choice' => new ProductionRule('choice', [
                new Production([
                    new Symbol('L', false),
                    new Symbol('O', false),
                    new Symbol('N', false),
                    new Symbol('G', false),
                ]),
                new Production([new Symbol('SHORT', false)]),
            ]),
        ]);
        $lengths = (new TerminationLengthComputer())->compute($grammar);
        $deriver = new TerminalDeriver(new FakerRandomSource(Factory::create()), 'stmt');

        self::assertSame(
            ['SHORT'],
            $deriver->derive($grammar, $lengths, new GenerationRequest(startRule: 'stmt', maxDepth: 3))->terminals,
        );
    }

    public function testDeriveThrowsOnDerivationLimit(): void
    {
        $grammar = new Grammar('infinite', [
            'infinite' => new ProductionRule('infinite', [
                new Production([
                    new Symbol('infinite', true),
                    new Symbol('a', false),
                ]),
            ]),
        ]);
        $deriver = new TerminalDeriver(new FakerRandomSource(Factory::create()), 'stmt');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Exceeded derivation limit while generating SQL.');

        $deriver->derive($grammar, new TerminationLengths(['infinite' => 1]), new GenerationRequest(startRule: 'infinite'));
    }

    public function testDeriveAllowsDerivationAtExactLimitBoundary(): void
    {
        $grammar = exactDerivationLimitGrammar();
        $deriver = new TerminalDeriver(new FakerRandomSource(Factory::create()), 'n0');

        self::assertSame(
            ['DONE'],
            $deriver->derive($grammar, (new TerminationLengthComputer())->compute($grammar), new GenerationRequest(startRule: 'n0'))->terminals,
        );
    }

    public function testDeriveThrowsOnEmptyAlternatives(): void
    {
        $grammar = new Grammar('empty', [
            'empty' => new ProductionRule('empty', []),
        ]);
        $deriver = new TerminalDeriver(new FakerRandomSource(Factory::create()), 'stmt');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Production rule has no alternatives.');

        $deriver->derive($grammar, new TerminationLengths(['empty' => 1]), new GenerationRequest(startRule: 'empty'));
    }

    public function testDeriveThrowsOnUnknownNonTerminalWhenLiteralFallbackIsDisabled(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Symbol('MISSING', true)]),
            ]),
        ]);
        $deriver = new TerminalDeriver(new FakerRandomSource(Factory::create()), 'stmt');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown grammar rule: MISSING');

        $deriver->derive($grammar, new TerminationLengths(['stmt' => 1]), new GenerationRequest());
    }

    public function testSqliteModeTreatsUnknownNonTerminalsAsLiterals(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Symbol('MISSING', true)]),
            ]),
        ]);

        $deriver = new TerminalDeriver(new FakerRandomSource(Factory::create()), 'stmt', true);

        self::assertSame(
            ['MISSING'],
            $deriver->derive($grammar, new TerminationLengths(['stmt' => 1]), new GenerationRequest())->terminals,
        );
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function providerRandomAlternativeSeeds(): iterable
    {
        yield 'seeds 0 and 4' => [0, 4];
        yield 'seeds 0 and 7' => [0, 7];
    }
}
