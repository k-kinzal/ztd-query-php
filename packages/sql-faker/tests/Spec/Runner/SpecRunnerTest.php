<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Spec\Runner;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Spec\Claim\ClaimDefinition;
use Spec\Claim\EvidenceDefinition;
use Spec\Policy\OutcomeKind;
use Spec\Policy\OutcomePolicy;
use Spec\Probe\EngineProbe;
use Spec\Probe\ProbePhase;
use Spec\Probe\ProbeResult;
use Spec\Runner\DialectRuntime;
use Spec\Runner\SpecRunner;
use Spec\Support\GrammarFingerprint;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\RewriteProgram;
use SqlFaker\Contract\RewriteStep;
use SqlFaker\Contract\Symbol;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Contract\TerminationLengths;

function snapshotGrammarFixture(): Grammar
{
    return new Grammar('stmt', [
        'stmt' => new ProductionRule('stmt', [
            new Production([new Symbol('raw_only', true)]),
        ]),
        'raw_only' => new ProductionRule('raw_only', [
            new Production([new Symbol('RAW', false)]),
        ]),
    ]);
}

function supportedGrammarFixture(): Grammar
{
    return new Grammar('stmt', [
        'stmt' => new ProductionRule('stmt', [
            new Production([new Symbol('SUPPORTED', false)]),
        ]),
    ]);
}

function fixtureRewriteProgram(): RewriteProgram
{
    return new RewriteProgram([
        new RewriteStep('fixture.step', 'fixture step'),
    ]);
}

#[CoversClass(SpecRunner::class)]
final class SpecRunnerTest extends TestCase
{
    public function testRunCanAssertOutcomePhaseAndReuseOneProbeObservationPerCase(): void
    {
        $runtime = new class (snapshotGrammarFixture(), supportedGrammarFixture()) implements DialectRuntime {
            public function __construct(
                private readonly Grammar $snapshot,
                private readonly Grammar $supportedGrammar,
            ) {
            }

            public function snapshot(): Grammar
            {
                return $this->snapshot;
            }

            public function supportedGrammar(): Grammar
            {
                return $this->supportedGrammar;
            }

            public function version(): string
            {
                return 'sqlite-3.47.2';
            }

            public function rewriteProgram(): RewriteProgram
            {
                return fixtureRewriteProgram();
            }

            public function terminationLengths(): TerminationLengths
            {
                return new TerminationLengths(['stmt' => 1]);
            }

            public function derive(GenerationRequest $request): TerminalSequence
            {
                return new TerminalSequence(['SELECT']);
            }

            public function generate(GenerationRequest $request): string
            {
                return 'SELECT * FROM _i0';
            }
        };
        $count = 0;
        $probe = new class ($count) implements EngineProbe {
            private int $count;

            public function __construct(int &$count)
            {
                $this->count = & $count;
            }

            public function dialect(): string
            {
                return 'sqlite';
            }

            public function observe(string $sql): ProbeResult
            {
                $this->count++;

                return ProbeResult::failed(ProbePhase::Prepare, null, null, 'no such table: _i0');
            }
        };
        $policy = new class () implements OutcomePolicy {
            public function dialect(): string
            {
                return 'sqlite';
            }

            public function classify(ProbeResult $probeResult): OutcomeKind
            {
                return OutcomeKind::State;
            }
        };

        $claim = new ClaimDefinition(
            'TEST-OUTCOME-PHASE-CLAIM',
            'spec',
            'sqlite',
            'outcome phase claim',
            '/tmp/claims.json[3]',
            'generation',
            ['start_rule' => 'stmt', 'max_depth' => 4],
            [['seed' => 17]],
            [
                new EvidenceDefinition('outcome.phase_is', ['phase' => 'prepare']),
                new EvidenceDefinition('outcome.kind_in', ['allowedKinds' => ['state']]),
            ],
        );

        /** @var array<string, mixed> $result */
        $result = (new SpecRunner(
            ['sqlite' => $runtime],
            ['sqlite' => $probe],
            ['sqlite' => $policy],
        ))->run([$claim])[0];

        self::assertSame('passed', $result['status']);
        self::assertSame(1, $count);
    }

    public function testRunUsesSnapshotSubjectForGrammarEvidenceAndFingerprintChecks(): void
    {
        $snapshot = snapshotGrammarFixture();
        $supportedGrammar = supportedGrammarFixture();

        $runtime = new class ($snapshot, $supportedGrammar) implements DialectRuntime {
            public function __construct(
                private readonly Grammar $snapshot,
                private readonly Grammar $supportedGrammar,
            ) {
            }

            public function snapshot(): Grammar
            {
                return $this->snapshot;
            }

            public function supportedGrammar(): Grammar
            {
                return $this->supportedGrammar;
            }

            public function version(): string
            {
                return 'mysql-8.4.7';
            }

            public function rewriteProgram(): RewriteProgram
            {
                return fixtureRewriteProgram();
            }

            public function terminationLengths(): TerminationLengths
            {
                return new TerminationLengths(['stmt' => 1]);
            }

            public function derive(GenerationRequest $request): TerminalSequence
            {
                return new TerminalSequence(['SELECT']);
            }

            public function generate(GenerationRequest $request): string
            {
                return 'SELECT';
            }
        };

        $claim = new ClaimDefinition(
            'TEST-SNAPSHOT-CLAIM',
            'contract',
            'mysql',
            'snapshot claim',
            '/tmp/claims.json[0]',
            'snapshot',
            [],
            [[]],
            [
                new EvidenceDefinition('grammar.entries.present', ['entries' => ['raw_only']]),
                new EvidenceDefinition('grammar.fingerprint_matches', [
                    'sha256_by_version' => [
                        'mysql-8.4.7' => GrammarFingerprint::sha256($snapshot),
                    ],
                ]),
            ],
        );

        /** @var array<string, mixed> $result */
        $result = (new SpecRunner(['mysql' => $runtime]))->run([$claim])[0];
        /** @var list<array<string, mixed>> $cases */
        $cases = $result['cases'];

        self::assertSame('passed', $result['status']);
        self::assertSame('passed', $cases[0]['status']);
    }

    public function testRunEvaluatesGrammarEvidenceAgainstSupportedGrammarWhenRequested(): void
    {
        $runtime = new class (snapshotGrammarFixture(), supportedGrammarFixture()) implements DialectRuntime {
            public function __construct(
                private readonly Grammar $snapshot,
                private readonly Grammar $supportedGrammar,
            ) {
            }

            public function snapshot(): Grammar
            {
                return $this->snapshot;
            }

            public function supportedGrammar(): Grammar
            {
                return $this->supportedGrammar;
            }

            public function version(): string
            {
                return 'mysql-8.4.7';
            }

            public function rewriteProgram(): RewriteProgram
            {
                return fixtureRewriteProgram();
            }

            public function terminationLengths(): TerminationLengths
            {
                return new TerminationLengths(['stmt' => 1]);
            }

            public function derive(GenerationRequest $request): TerminalSequence
            {
                return new TerminalSequence(['SELECT']);
            }

            public function generate(GenerationRequest $request): string
            {
                return 'SELECT';
            }
        };

        $claim = new ClaimDefinition(
            'TEST-GRAMMAR-CLAIM',
            'contract',
            'mysql',
            'grammar claim',
            '/tmp/claims.json[1]',
            'grammar',
            [],
            [[]],
            [
                new EvidenceDefinition('grammar.entries.present', ['entries' => ['raw_only']]),
            ],
        );

        /** @var array<string, mixed> $result */
        $result = (new SpecRunner(['mysql' => $runtime]))->run([$claim])[0];
        /** @var list<array<string, mixed>> $cases */
        $cases = $result['cases'];
        /** @var list<array<string, mixed>> $checks */
        $checks = $cases[0]['checks'];
        /** @var array<string, mixed> $facts */
        $facts = $checks[0]['facts'];
        /** @var list<string> $missingEntries */
        $missingEntries = $facts['missing_entries'];

        self::assertSame('failed', $result['status']);
        self::assertSame(['raw_only'], $missingEntries);
    }

    public function testRunEvaluatesRewriteStepsAndTerminationLengthsAgainstRuntimeContract(): void
    {
        $runtime = new class (snapshotGrammarFixture(), supportedGrammarFixture()) implements DialectRuntime {
            public function __construct(
                private readonly Grammar $snapshot,
                private readonly Grammar $supportedGrammar,
            ) {
            }

            public function snapshot(): Grammar
            {
                return $this->snapshot;
            }

            public function supportedGrammar(): Grammar
            {
                return $this->supportedGrammar;
            }

            public function version(): string
            {
                return 'mysql-8.4.7';
            }

            public function rewriteProgram(): RewriteProgram
            {
                return fixtureRewriteProgram();
            }

            public function terminationLengths(): TerminationLengths
            {
                return new TerminationLengths(['stmt' => 1]);
            }

            public function derive(GenerationRequest $request): TerminalSequence
            {
                return new TerminalSequence(['SELECT']);
            }

            public function generate(GenerationRequest $request): string
            {
                return 'SELECT';
            }
        };

        $claim = new ClaimDefinition(
            'TEST-RUNTIME-CONTRACT-CLAIM',
            'contract',
            'mysql',
            'runtime contract claim',
            '/tmp/claims.json[2]',
            'grammar',
            [],
            [[]],
            [
                new EvidenceDefinition('grammar.rewrite_steps_match', ['step_ids' => ['fixture.step']]),
                new EvidenceDefinition('grammar.termination_lengths_match', ['lengths' => ['stmt' => 1]]),
            ],
        );

        /** @var array<string, mixed> $result */
        $result = (new SpecRunner(['mysql' => $runtime]))->run([$claim])[0];

        self::assertSame('passed', $result['status']);
    }
}
