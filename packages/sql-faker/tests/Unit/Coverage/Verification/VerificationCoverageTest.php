<?php

declare (strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage\Verification;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\CoverageSets;
use SqlFaker\Coverage\CoverageSnapshotStore;
use SqlFaker\Coverage\GenerationTrace;
use SqlFaker\Coverage\GeneratorRevision;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Coverage\GrammarCoverageInventory;
use SqlFaker\Coverage\LexicalObservation;
use SqlFaker\Coverage\SequenceObservation;
use SqlFaker\Coverage\SnapshotValidation;
use SqlFaker\Coverage\Verification\SqlContext;
use SqlFaker\Coverage\Verification\VerificationCoverage;
use SqlFaker\Coverage\Verification\VerificationEvent;
use SqlFaker\Coverage\Verification\VerificationResult;
use SqlFaker\Coverage\Verification\VerificationWitnessStore;
use SqlFaker\Grammar\Choice\ByteChoices;
use SqlFaker\Grammar\Derivation\CompletionCosts;
use SqlFaker\Grammar\Derivation\DerivationNode;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\BoundaryCompletion;
use SqlFaker\Grammar\Generation\Output\CandidateResolver;
use SqlFaker\Grammar\Generation\Output\OutputPart;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\ValueChoices;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(VerificationCoverage::class)]
#[UsesClass(GrammarCoverage::class)]
#[UsesClass(VerificationEvent::class)]
#[UsesClass(VerificationResult::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(GrammarCoverageInventory::class)]
#[UsesClass(CoverageException::class)]
#[UsesClass(GeneratorRevision::class)]
#[UsesClass(CoverageSnapshotStore::class)]
#[UsesClass(SnapshotValidation::class)]
#[UsesClass(GenerationTrace::class)]
#[UsesClass(CoverageSets::class)]
#[UsesClass(ByteChoices::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(DerivationNode::class)]
#[UsesClass(SequenceObservation::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(ChoiceLexemeGenerator::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeCandidates::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(LexemeSequence::class)]
#[UsesClass(ValueLexemeGenerator::class)]
#[UsesClass(CandidateResolver::class)]
#[UsesClass(OutputPart::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(ReverseLexemeGenerator::class)]
#[UsesClass(CombinedSpacingRule::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(LexicalObservation::class)]
#[UsesClass(ValueChoices::class)]
#[UsesClass(BoundaryCompletion::class)]
#[UsesClass(SqlContext::class)]
#[UsesClass(VerificationWitnessStore::class)]
#[UsesClass(CharacterDomain::class)]
final class VerificationCoverageTest extends TestCase
{
    public function testRecordSeparatesAcceptedInconclusiveAndUnsupportedObservations(): void
    {
        $grammar = new GrammarCoverage(null);
        $grammar->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        $grammar->beginGeneration('stmt', []);
        $grammar->beginAttempt(0);
        $grammar->record(0, null, null, 'stmt', $grammar->inventory()->denominator[0], 'selected');
        $grammar->commitAttempt(hash('sha256', 'SELECT 1'));
        $grammar->endGeneration();
        $coverage = new VerificationCoverage($grammar, 'oracle-a', ['version' => 'test-v1']);
        $coverage->record('SELECT 1', 'first', new VerificationResult('semantic-inconclusive', '42P01'));
        self::assertSame(0.0, $coverage->snapshot()['acceptedRate']);
        $coverage->record('SELECT 1', 'second', new VerificationResult('unsupported', '0A000'));
        self::assertSame(0.0, $coverage->snapshot()['acceptedRate']);
        $coverage->record('SELECT 1', 'third', new VerificationResult('accepted'));
        self::assertSame(0.2, $coverage->snapshot()['acceptedRate']);
        self::assertCount(3, $coverage->snapshot()['coverage']);
        self::assertCount(3, $coverage->snapshot()['witnesses']);
        self::assertSame(hash('sha256', 'third'), $coverage->snapshot()['last']['inputHash'] ?? null);
        self::assertSame(hash('sha256', 'SELECT 1'), $coverage->snapshot()['last']['sqlHash']);
    }

    public function testRecordRejectsAnObservationForDifferentSql(): void
    {
        $grammarCoverage = new GrammarCoverage(null);
        $grammarCoverage->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        $grammarCoverage->beginGeneration('stmt', []);
        $grammarCoverage->beginAttempt(0);
        $grammarCoverage->record(0, null, null, 'stmt', $grammarCoverage->inventory()->denominator[0], 'selected');
        $grammarCoverage->commitAttempt(hash('sha256', 'SELECT 1'));
        $grammarCoverage->endGeneration();
        $coverage = new VerificationCoverage($grammarCoverage, 'oracle-a', []);
        $this->expectException(CoverageException::class);
        $coverage->record('SELECT 2', 'input', new VerificationResult('accepted'));
    }

    public function testRecordRejectsAnObservationBeforeGeneration(): void
    {
        $grammarCoverage = new GrammarCoverage(null);
        $grammarCoverage->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        $coverage = new VerificationCoverage($grammarCoverage, 'oracle-a', []);
        $this->expectException(CoverageException::class);
        $coverage->record('SELECT 1', 'input', new VerificationResult('accepted'));
    }

    public function testSnapshotKeepsTheOriginalDenominatorAndFirstWitness(): void
    {
        $grammar = new GrammarCoverage(null);
        $grammar->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        $grammar->beginGeneration('stmt', []);
        $grammar->beginAttempt(0);
        $grammar->record(0, null, null, 'stmt', $grammar->inventory()->denominator[0], 'selected');
        $grammar->commitAttempt(hash('sha256', 'SELECT 1'));
        $grammar->endGeneration();
        $coverage = new VerificationCoverage($grammar, 'oracle-a', ['version' => 'test-v1']);
        $coverage->record('SELECT 1', 'first', new VerificationResult('accepted'));
        $coverage->record('SELECT 1', 'second', new VerificationResult('accepted'));
        self::assertSame($grammar->inventory()->denominator, $coverage->snapshot()['denominatorIds']);
        self::assertCount(1, $coverage->snapshot()['witnesses']);
        self::assertSame(hash('sha256', 'first'), $coverage->snapshot()['witnesses'][0]['inputHash']);
        self::assertSame(2, $coverage->snapshot()['observationsInRun']);
        $coverage->flush();
    }

    public function testFlushRestoresCompatibleHistoryWithoutCopyingRunCounters(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-coverage-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($directory);
        $grammar = new GrammarCoverage(null);
        $grammar->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        $grammar->beginGeneration('stmt', []);
        $grammar->beginAttempt(0);
        $grammar->record(0, null, null, 'stmt', $grammar->inventory()->denominator[0], 'selected');
        $grammar->commitAttempt(hash('sha256', 'SELECT 1'));
        $grammar->endGeneration();
        $coverage = new VerificationCoverage($grammar, 'oracle-a', ['version' => 'test-v1'], $directory);
        $coverage->record('SELECT 1', 'first', new VerificationResult('accepted'));
        $coverage->flush();
        $coverage->flush();
        unset($coverage);
        $restored = new VerificationCoverage($grammar, 'oracle-a', ['version' => 'test-v1'], $directory);
        self::assertSame(0.2, $restored->snapshot()['acceptedRate']);
        self::assertSame(0, $restored->snapshot()['observationsInRun']);
        self::assertNull($restored->snapshot()['last']);
        $differentOracle = new VerificationCoverage($grammar, 'oracle-b', ['version' => 'test-v1'], $directory);
        $differentMode = new VerificationCoverage($grammar, 'oracle-a', ['version' => 'test-v2'], $directory);
        self::assertSame(0.0, $differentOracle->snapshot()['acceptedRate']);
        self::assertSame(0.0, $differentMode->snapshot()['acceptedRate']);
        unset($restored, $differentOracle, $differentMode);
        (new Filesystem())->remove($directory);
    }

    /**
     * @throws JsonException
     */

    public function testMergeUnionsCompatibleWitnessesIdempotently(): void
    {
        $grammarCoverage = new GrammarCoverage(null);
        $grammarCoverage->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        $grammarCoverage->beginGeneration('stmt', []);
        $grammarCoverage->beginAttempt(0);
        $grammarCoverage->record(0, null, null, 'stmt', $grammarCoverage->inventory()->denominator[0], 'selected');
        $grammarCoverage->commitAttempt(hash('sha256', 'SELECT 1'));
        $grammarCoverage->endGeneration();
        $source = new VerificationCoverage($grammarCoverage, 'oracle-a', []);
        $source->record('SELECT 1', 'input', new VerificationResult('accepted'));
        $grammarCoverage = new GrammarCoverage(null);
        $grammarCoverage->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        $grammarCoverage->beginGeneration('stmt', []);
        $grammarCoverage->beginAttempt(0);
        $grammarCoverage->record(0, null, null, 'stmt', $grammarCoverage->inventory()->denominator[0], 'selected');
        $grammarCoverage->commitAttempt(hash('sha256', 'SELECT 1'));
        $grammarCoverage->endGeneration();
        $destination = new VerificationCoverage($grammarCoverage, 'oracle-a', []);
        $json = json_encode($source->snapshot(), JSON_THROW_ON_ERROR);
        $destination->merge($json);
        $destination->merge($json);
        self::assertSame(0.2, $destination->snapshot()['acceptedRate']);
        self::assertCount(1, $destination->snapshot()['witnesses']);
        self::assertSame(0, $destination->snapshot()['observationsInRun']);
    }
    #[DataProvider('providerInvalidSnapshots')]

    public function testMergeRejectsCorruptOrIncompatibleHistory(string $json): void
    {
        $grammarCoverage = new GrammarCoverage(null);
        $grammarCoverage->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        $grammarCoverage->beginGeneration('stmt', []);
        $grammarCoverage->beginAttempt(0);
        $grammarCoverage->record(0, null, null, 'stmt', $grammarCoverage->inventory()->denominator[0], 'selected');
        $grammarCoverage->commitAttempt(hash('sha256', 'SELECT 1'));
        $grammarCoverage->endGeneration();
        $coverage = new VerificationCoverage($grammarCoverage, 'oracle-a', []);
        $this->expectException(CoverageException::class);
        $coverage->merge($json);
    }

    /**
     * @return iterable<array{string}>
     * @throws JsonException
     */

    public static function providerInvalidSnapshots(): iterable
    {
        yield ['{broken'];
        yield ['[]'];
        $grammarCoverage = new GrammarCoverage(null);
        $grammarCoverage->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        $grammarCoverage->beginGeneration('stmt', []);
        $grammarCoverage->beginAttempt(0);
        $grammarCoverage->record(0, null, null, 'stmt', $grammarCoverage->inventory()->denominator[0], 'selected');
        $grammarCoverage->commitAttempt(hash('sha256', 'SELECT 1'));
        $grammarCoverage->endGeneration();
        $source = new VerificationCoverage($grammarCoverage, 'oracle-a', []);
        $source->record('SELECT 1', 'input', new VerificationResult('accepted'));
        $snapshot = $source->snapshot();
        yield [json_encode([...$snapshot, 'formatVersion' => 9], JSON_THROW_ON_ERROR)];
        yield [
            json_encode([...$snapshot, 'identity' => [...$snapshot['identity'], 'oracleRevision' => 'old']], JSON_THROW_ON_ERROR),
        ];
        yield [
            json_encode([...$snapshot, 'witnesses' => [[...$snapshot['witnesses'][0], 'id' => 'unknown']]], JSON_THROW_ON_ERROR),
        ];
        yield [
            json_encode([...$snapshot, 'witnesses' => [[...$snapshot['witnesses'][0], 'sqlHash' => 'invalid']]], JSON_THROW_ON_ERROR),
        ];
    }

    public function testRecordAssociatesCheckedWrappersWithTheOriginalGeneratedFragment(): void
    {
        $grammar = new GrammarCoverage(null);
        $grammar->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        $grammar->beginGeneration('stmt', []);
        $grammar->beginAttempt(0);
        $grammar->record(0, null, null, 'stmt', $grammar->inventory()->denominator[0], 'selected');
        $grammar->commitAttempt(hash('sha256', '1'));
        $grammar->endGeneration();
        $coverage = new VerificationCoverage($grammar, 'oracle', ['prefix' => 'SELECT ', 'suffix' => ' AS value']);
        $coverage->record('SELECT 1 AS value', 'input', new VerificationResult('accepted'));
        self::assertSame(hash('sha256', 'SELECT 1 AS value'), $coverage->snapshot()['witnesses'][0]['sqlHash']);
        $last = $coverage->snapshot()['last'];
        self::assertNotNull($last);
        self::assertSame(hash('sha256', '1'), $last['trace']['attempts'][0]['sqlHash']);
        self::assertSame('SELECT ', $coverage->snapshot()['identity']['configuration']['prefix']);
    }

    public function testFlushRestoresEveryFeatureKindWithoutLosingTheirVerdictSeparation(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-coverage-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($directory);
        $grammar = new GrammarCoverage(null);
        $grammar->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    [
                        'stmt' => new ProductionRule(
                            'stmt',
                            [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])],
                        ),
                        'expr' => new ProductionRule(
                            'expr',
                            [
                                new Production([new Terminal('1')]),
                                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                                new Production([]),
                            ],
                        ),
                        'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
                    ],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        $grammar->beginGeneration('stmt', []);
        $grammar->beginAttempt(0);
        $grammar->record(0, null, null, 'stmt', $grammar->inventory()->denominator[0], 'selected');
        $sequence = TerminalSequence::fromNames(['OLD']);
        $changed = $sequence->replace(0, 1, [$sequence->terminals[0]->replaced('PAIR', 'fixture-rewrite')], 'fixture-rewrite');
        $grammar->recordSequence($changed);
        $left = new Lexeme('A', 'identifier', $changed->terminals[0], 'name-left');
        $right = new Lexeme('B', 'identifier', $changed->terminals[0], 'name-right');
        $candidate = new LexemeSequence([$left, $right], 'pair:A+B', provenance: static fn (): iterable => ['pair', 'version-case:fixture-v1']);
        $grammar->recordOutput(
            new ResolvedOutput(
                [new OutputPart($left, ' ', $candidate->id, ['identifier-boundary']), new OutputPart($right, '', $candidate->id)],
                candidates: [$candidate],
                rejections: [['index' => 0, 'candidate' => 'bad-pair', 'rules' => ['identifier-boundary']]],
            ),
        );
        $grammar->commitAttempt(hash('sha256', 'A B'));
        $grammar->endGeneration();
        $coverage = new VerificationCoverage($grammar, 'oracle', [], $directory);
        $coverage->record('A B', 'input', new VerificationResult('accepted'));
        $coverage->record('A B', 'inconclusive-input', new VerificationResult('semantic-inconclusive', '42P01'));
        $coverage->record('A B', 'unavailable-input', new VerificationResult('infrastructure-failure', 'connection'));
        $snapshot = $coverage->snapshot();
        self::assertSame(['name-left', 'name-right'], $snapshot['coverage']['accepted']['lexeme']);
        self::assertSame(['name-left + name-right'], $snapshot['coverage']['accepted']['compound']);
        self::assertSame(['fixture-v1'], $snapshot['coverage']['accepted']['version-case']);
        self::assertSame(['fixture-rewrite'], $snapshot['coverage']['accepted']['rewrite']);
        self::assertSame(['identifier-boundary:space', 'default:join'], $snapshot['coverage']['accepted']['spacing']);
        self::assertNotNull($snapshot['last']);
        self::assertSame('OLD', $snapshot['last']['trace']['rewriteOperations'][0]['removed'][0]['name']);
        self::assertSame('PAIR', $snapshot['last']['trace']['rewriteOperations'][0]['inserted'][0]['name']);
        self::assertSame(['pair', 'version-case:fixture-v1'], $snapshot['last']['trace']['candidateSources'][0]);
        self::assertSame('bad-pair', $snapshot['last']['trace']['candidateRejections'][0]['candidate']);
        $coverage->flush();
        unset($coverage);
        $restored = new VerificationCoverage($grammar, 'oracle', [], $directory);
        self::assertSame($snapshot['coverage'], $restored->snapshot()['coverage']);
        self::assertSame($snapshot['witnesses'], $restored->snapshot()['witnesses']);
        self::assertSame(0.2, $restored->snapshot()['acceptedRate']);
        unset($restored);
        (new Filesystem())->remove($directory);
    }
}
