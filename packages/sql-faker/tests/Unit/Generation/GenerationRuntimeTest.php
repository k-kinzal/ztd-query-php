<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\RewriteProgram;
use SqlFaker\Contract\RewriteStep;
use SqlFaker\Contract\SnapshotLoader;
use SqlFaker\Contract\SupportedGrammarBuilder;
use SqlFaker\Contract\Symbol;
use SqlFaker\Contract\TerminalDeriver;
use SqlFaker\Contract\TerminalRenderer;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Contract\TerminationLengthComputer;
use SqlFaker\Contract\TerminationLengths;
use SqlFaker\Contract\TokenJoiner;
use SqlFaker\Contract\TokenSequence;
use SqlFaker\Generation\GenerationRuntime;

#[CoversClass(GenerationRuntime::class)]
#[UsesClass(GenerationRequest::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(RewriteProgram::class)]
#[UsesClass(RewriteStep::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(TerminationLengths::class)]
#[UsesClass(TokenSequence::class)]
final class GenerationRuntimeTest extends TestCase
{
    public function testTerminationLengthsRemainsPartOfThePublicRuntimeApi(): void
    {
        self::assertTrue((new ReflectionMethod(GenerationRuntime::class, 'terminationLengths'))->isPublic());
    }

    public function testTerminationLengthsCanBeRetrievedThroughThePublicRuntimeApi(): void
    {
        $snapshot = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Symbol('SELECT', false)]),
            ]),
        ]);
        $lengths = new TerminationLengths(['stmt' => 1]);
        /** @var \ArrayObject<string, int> $calls */
        $calls = new \ArrayObject(['compute' => 0]);
        $runtime = new GenerationRuntime(
            new class ($snapshot) implements SnapshotLoader {
                public function __construct(private readonly Grammar $snapshot)
                {
                }

                public function load(): Grammar
                {
                    return $this->snapshot;
                }

                public function version(): string
                {
                    return 'fixture-1.0';
                }
            },
            new class ($snapshot) implements SupportedGrammarBuilder {
                public function __construct(private readonly Grammar $snapshot)
                {
                }

                public function build(Grammar $snapshot): Grammar
                {
                    return $this->snapshot;
                }

                public function rewriteProgram(): RewriteProgram
                {
                    return new RewriteProgram([
                        new RewriteStep('fixture', 'fixture step'),
                    ]);
                }
            },
            new class ($calls, $lengths) implements TerminationLengthComputer {
                /**
                 * @param \ArrayObject<string, int> $calls
                 */
                public function __construct(private readonly \ArrayObject $calls, private readonly TerminationLengths $lengths)
                {
                }

                public function compute(Grammar $grammar): TerminationLengths
                {
                    $this->calls['compute'] = (int) $this->calls['compute'] + 1;

                    return $this->lengths;
                }
            },
            new class () implements TerminalDeriver {
                public function derive(Grammar $grammar, TerminationLengths $terminationLengths, GenerationRequest $request): TerminalSequence
                {
                    return new TerminalSequence(['SELECT']);
                }
            },
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

        self::assertSame($lengths, $runtime->terminationLengths());
        self::assertSame($lengths, $runtime->terminationLengths());
        self::assertSame(1, $calls['compute']);
    }

    public function testGenerationRuntimeCachesGrammarPhaseResultsAndUsesGenerationPipeline(): void
    {
        $snapshot = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Symbol('SELECT', false)]),
            ]),
        ]);
        $rewriteProgram = new RewriteProgram([
            new RewriteStep('fixture', 'fixture step'),
        ]);
        /** @var \ArrayObject<string, int> $calls */
        $calls = new \ArrayObject([
            'load' => 0,
            'build' => 0,
            'compute' => 0,
            'derive' => 0,
            'render' => 0,
            'join' => 0,
        ]);

        $runtime = new GenerationRuntime(
            new class ($snapshot, $calls) implements SnapshotLoader {
                /**
                 * @param \ArrayObject<string, int> $calls
                 */
                public function __construct(private readonly Grammar $snapshot, private readonly \ArrayObject $calls)
                {
                }

                public function load(): Grammar
                {
                    $this->calls['load'] = (int) $this->calls['load'] + 1;

                    return $this->snapshot;
                }

                public function version(): string
                {
                    return 'fixture-1.0';
                }
            },
            new class ($calls, $rewriteProgram) implements SupportedGrammarBuilder {
                /**
                 * @param \ArrayObject<string, int> $calls
                 */
                public function __construct(private readonly \ArrayObject $calls, private readonly RewriteProgram $rewriteProgram)
                {
                }

                public function build(Grammar $snapshot): Grammar
                {
                    $this->calls['build'] = (int) $this->calls['build'] + 1;

                    return $snapshot;
                }

                public function rewriteProgram(): RewriteProgram
                {
                    return $this->rewriteProgram;
                }
            },
            new class ($calls) implements TerminationLengthComputer {
                /**
                 * @param \ArrayObject<string, int> $calls
                 */
                public function __construct(private readonly \ArrayObject $calls)
                {
                }

                public function compute(Grammar $grammar): TerminationLengths
                {
                    $this->calls['compute'] = (int) $this->calls['compute'] + 1;

                    return new TerminationLengths(['stmt' => 1]);
                }
            },
            new class ($calls) implements TerminalDeriver {
                /**
                 * @param \ArrayObject<string, int> $calls
                 */
                public function __construct(private readonly \ArrayObject $calls)
                {
                }

                public function derive(Grammar $grammar, TerminationLengths $terminationLengths, GenerationRequest $request): TerminalSequence
                {
                    $this->calls['derive'] = (int) $this->calls['derive'] + 1;

                    return new TerminalSequence(['SELECT']);
                }
            },
            new class ($calls) implements TerminalRenderer {
                /**
                 * @param \ArrayObject<string, int> $calls
                 */
                public function __construct(private readonly \ArrayObject $calls)
                {
                }

                public function render(TerminalSequence $terminals): TokenSequence
                {
                    $this->calls['render'] = (int) $this->calls['render'] + 1;

                    return new TokenSequence($terminals->terminals);
                }
            },
            new class ($calls) implements TokenJoiner {
                /**
                 * @param \ArrayObject<string, int> $calls
                 */
                public function __construct(private readonly \ArrayObject $calls)
                {
                }

                public function join(TokenSequence $tokens): string
                {
                    $this->calls['join'] = (int) $this->calls['join'] + 1;

                    return implode(' ', $tokens->tokens);
                }
            },
        );

        self::assertSame($snapshot, $runtime->snapshot());
        self::assertSame($snapshot, $runtime->snapshot());
        self::assertSame($snapshot, $runtime->supportedGrammar());
        self::assertSame('fixture-1.0', $runtime->version());
        self::assertSame(['fixture'], $runtime->rewriteProgram()->stepIds());
        self::assertSame(['SELECT'], $runtime->derive(new GenerationRequest(startRule: 'stmt', seed: 11, maxDepth: 1))->terminals);
        self::assertSame('SELECT', $runtime->generate(new GenerationRequest(startRule: 'stmt', seed: 11, maxDepth: 1)));
        self::assertSame('SELECT', $runtime->generate(new GenerationRequest(startRule: 'stmt', seed: 11, maxDepth: 1)));
        self::assertSame(1, $calls['load']);
        self::assertSame(1, $calls['build']);
        self::assertSame(1, $calls['compute']);
        self::assertSame(3, $calls['derive']);
        self::assertSame(2, $calls['render']);
        self::assertSame(2, $calls['join']);
    }
}
