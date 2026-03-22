<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Spec\Runner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Spec\Adapter\GenerationRuntimeAdapter;
use Spec\Claim\ClaimDefinition;
use Spec\Claim\EvidenceDefinition;
use Spec\Runner\SpecRunner;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\RandomSource;
use SqlFaker\Contract\RewriteProgram;
use SqlFaker\Contract\RewriteStep;
use SqlFaker\Contract\SnapshotLoader;
use SqlFaker\Contract\SupportedGrammarBuilder;
use SqlFaker\Contract\Symbol;
use SqlFaker\Contract\TerminalRenderer;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Contract\TerminationLengths;
use SqlFaker\Contract\TokenJoiner;
use SqlFaker\Contract\TokenSequence;
use SqlFaker\Generation\GenerationRuntime;
use SqlFaker\Generation\TerminalDeriver;
use SqlFaker\Generation\TerminationLengthComputer;

function algorithmFixtureRuntime(Grammar $grammar, RandomSource $random, string $version): GenerationRuntimeAdapter
{
    $rewriteProgram = new RewriteProgram([
        new RewriteStep('fixture.algorithm', 'fixture algorithm step'),
    ]);
    $runtime = new GenerationRuntime(
        new class ($grammar, $version) implements SnapshotLoader {
            public function __construct(private readonly Grammar $grammar, private readonly string $version)
            {
            }

            public function version(): string
            {
                return $this->version;
            }

            public function load(): Grammar
            {
                return $this->grammar;
            }
        },
        new class ($grammar, $rewriteProgram) implements SupportedGrammarBuilder {
            public function __construct(private readonly Grammar $grammar, private readonly RewriteProgram $rewriteProgram)
            {
            }

            public function build(Grammar $snapshot): Grammar
            {
                return $this->grammar;
            }

            public function rewriteProgram(): RewriteProgram
            {
                return $this->rewriteProgram;
            }
        },
        new TerminationLengthComputer(),
        new TerminalDeriver($random, $grammar->startSymbol),
        new class () implements TerminalRenderer {
            public function render(TerminalSequence $terminals): TokenSequence
            {
                return new TokenSequence($terminals->terminals);
            }
        },
        new class () implements TokenJoiner {
            public function join(TokenSequence $tokens): string
            {
                return implode(' ', $tokens->tokens);
            }
        },
    );

    return new GenerationRuntimeAdapter($runtime);
}

/**
 * @param list<int> $values
 */
function arrayRandomSource(array $values): RandomSource
{
    return new class ($values) implements RandomSource {
        /**
         * @param list<int> $values
         */
        public function __construct(
            private array $values,
        ) {
        }

        public function seed(int $seed): void
        {
        }

        public function numberBetween(int $min, int $max): int
        {
            $value = array_shift($this->values);
            $candidate = is_int($value) ? $value : $min;

            return max($min, min($max, $candidate));
        }

        public function stringElement(array $elements): string
        {
            return $elements[0];
        }
    };
}

function leftmostAlgorithmGrammar(): Grammar
{
    return new Grammar('stmt', [
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
}

function targetDepthAlgorithmGrammar(): Grammar
{
    return new Grammar('stmt', [
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
}

function infiniteAlgorithmGrammar(): Grammar
{
    return new Grammar('infinite', [
        'infinite' => new ProductionRule('infinite', [
            new Production([
                new Symbol('infinite', true),
                new Symbol('a', false),
            ]),
        ]),
    ]);
}

#[CoversClass(SpecRunner::class)]
final class SpecRunnerAlgorithmContractTest extends TestCase
{
    public function testClaimsCanAssertLeftmostDerivationTerminalOrder(): void
    {
        $runtime = algorithmFixtureRuntime(leftmostAlgorithmGrammar(), arrayRandomSource([0, 1, 0]), 'fixture-leftmost');
        $claim = new ClaimDefinition(
            'FIXTURE-LEFTMOST-DERIVATION',
            'contract',
            'fixture',
            'leftmost derivation claim',
            '/tmp/algorithm-claims.json[0]',
            'generation',
            ['start_rule' => 'stmt'],
            [['seed' => 17]],
            [
                new EvidenceDefinition('generation.terminals_equal', ['terminals' => ['B', '1']]),
            ],
        );

        /** @var array<string, mixed> $result */
        $result = (new SpecRunner(['fixture' => $runtime]))->run([$claim])[0];

        self::assertSame('passed', $result['status']);
    }

    public function testClaimsCanAssertShortestSelectionAtExactTargetDepth(): void
    {
        $runtime = algorithmFixtureRuntime(targetDepthAlgorithmGrammar(), arrayRandomSource([0]), 'fixture-target-depth');
        $claim = new ClaimDefinition(
            'FIXTURE-TARGET-DEPTH-SWITCH',
            'contract',
            'fixture',
            'target depth switch claim',
            '/tmp/algorithm-claims.json[1]',
            'generation',
            ['start_rule' => 'stmt', 'max_depth' => 3],
            [['seed' => 17]],
            [
                new EvidenceDefinition('generation.terminals_equal', ['terminals' => ['SHORT']]),
            ],
        );

        /** @var array<string, mixed> $result */
        $result = (new SpecRunner(['fixture' => $runtime]))->run([$claim])[0];

        self::assertSame('passed', $result['status']);
    }

    public function testClaimsCanAssertDerivationLimitFailures(): void
    {
        $runtime = algorithmFixtureRuntime(infiniteAlgorithmGrammar(), arrayRandomSource([0]), 'fixture-derivation-limit');
        $claim = new ClaimDefinition(
            'FIXTURE-DERIVATION-LIMIT',
            'contract',
            'fixture',
            'derivation limit claim',
            '/tmp/algorithm-claims.json[2]',
            'generation',
            ['start_rule' => 'infinite'],
            [['seed' => 17]],
            [
                new EvidenceDefinition('generation.fails', ['pattern' => '/Exceeded derivation limit while generating SQL\\./']),
            ],
        );

        /** @var array<string, mixed> $result */
        $result = (new SpecRunner(['fixture' => $runtime]))->run([$claim])[0];

        self::assertSame('passed', $result['status']);
    }
}
