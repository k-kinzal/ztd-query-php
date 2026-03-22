<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Spec\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Spec\Adapter\GenerationRuntimeAdapter;
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

#[CoversClass(GenerationRuntimeAdapter::class)]
final class GenerationRuntimeAdapterTest extends TestCase
{
    public function testAdapterDelegatesSnapshotSupportedGrammarVersionAndGeneration(): void
    {
        $snapshot = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Symbol('SNAPSHOT', false)]),
            ]),
        ]);
        $supportedGrammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Symbol('SUPPORTED', false)]),
            ]),
        ]);
        $rewriteProgram = new RewriteProgram([
            new RewriteStep('fixture', 'fixture step'),
        ]);

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
                    return 'mysql-8.4.7';
                }
            },
            new class ($supportedGrammar, $rewriteProgram) implements SupportedGrammarBuilder {
                public function __construct(private readonly Grammar $supportedGrammar, private readonly RewriteProgram $rewriteProgram)
                {
                }

                public function build(Grammar $snapshot): Grammar
                {
                    return $this->supportedGrammar;
                }

                public function rewriteProgram(): RewriteProgram
                {
                    return $this->rewriteProgram;
                }
            },
            new class () implements TerminationLengthComputer {
                public function compute(Grammar $grammar): TerminationLengths
                {
                    return new TerminationLengths(['stmt' => 1]);
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

        $adapter = new GenerationRuntimeAdapter($runtime);

        self::assertSame($snapshot, $adapter->snapshot());
        self::assertSame($supportedGrammar, $adapter->supportedGrammar());
        self::assertSame(['fixture'], $adapter->rewriteProgram()->stepIds());
        self::assertSame(1, $adapter->terminationLengths()->lengthOf('stmt'));
        self::assertSame('mysql-8.4.7', $adapter->version());
        self::assertSame(['SELECT'], $adapter->derive(new GenerationRequest(startRule: 'stmt', seed: 17, maxDepth: 1))->terminals);
        self::assertSame('SELECT', $adapter->generate(new GenerationRequest(startRule: 'stmt', seed: 17, maxDepth: 1)));
    }
}
