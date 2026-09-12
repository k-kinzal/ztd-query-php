<?php

declare (strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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
use SqlFaker\Generation\Candidate\ChoiceLexemeGenerator;
use SqlFaker\Generation\Candidate\FixedLexemeGenerator;
use SqlFaker\Generation\Candidate\ValueLexemeGenerator;
use SqlFaker\Generation\Choice\ByteChoices;
use SqlFaker\Generation\Derivation\CompletionCosts;
use SqlFaker\Generation\Derivation\DerivationNode;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\LexemeSequence;
use SqlFaker\Generation\Lexeme\OutputPart;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Lexeme\SpacingConstraint;
use SqlFaker\Generation\Output\BoundaryCompletion;
use SqlFaker\Generation\Output\CandidateResolver;
use SqlFaker\Generation\Output\CombinedSpacingRule;
use SqlFaker\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\Generation\Value\CharacterDomain;
use SqlFaker\Generation\Value\ValueChoices;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(GrammarCoverage::class)]
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
#[UsesClass(CharacterDomain::class)]
#[UsesClass(FixedLexemeGenerator::class)]
final class GrammarCoverageTest extends TestCase
{
    public function testSnapshotSeparatesReachedFromEmittedAndKeepsTheFullDenominator(): void
    {
        $coverage = new GrammarCoverage(null);
        $coverage->register(
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
        $recordedProductionIds = $coverage->inventory()->denominator;
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[0], 'input');
        $coverage->discardAttempt('lexical failure');
        $coverage->beginAttempt(1);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[1], 'input');
        $coverage->commitAttempt('sql-hash');
        $coverage->endGeneration();
        self::assertSame(5, $coverage->snapshot()['current']['total']);
        self::assertSame(2, $coverage->snapshot()['current']['reached']);
        self::assertSame(1, $coverage->snapshot()['current']['emitted']);
        self::assertSame(3, count($coverage->snapshot()['current']['notReachedIds']));
        self::assertSame(0.4, $coverage->snapshot()['current']['reachedRate']);
    }

    public function testFlushRestoresOnlyCumulativeHistoryAndPreservesCurrentObservations(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-coverage-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($directory);
        $coverage = new GrammarCoverage($directory);
        $coverage->register(
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
        $recordedProductionIds = $coverage->inventory()->denominator;
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[0], 'input');
        $coverage->discardAttempt('lexical failure');
        $coverage->beginAttempt(1);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[1], 'input');
        $coverage->commitAttempt('sql-hash');
        $coverage->endGeneration();
        $coverage->flush();
        $coverage->flush();
        self::assertSame(2, $coverage->snapshot()['current']['reached']);
        unset($coverage);
        $restored = new GrammarCoverage($directory);
        $restored->register(
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
        self::assertSame(2, $restored->snapshot()['cumulative']['reached']);
        self::assertSame(0, $restored->snapshot()['current']['reached']);
        self::assertNull($restored->lastGeneration());
        $recordedProductionIds = $restored->inventory()->denominator;
        $restored->beginGeneration('stmt', []);
        $restored->beginAttempt(0);
        $restored->record(0, null, null, 'stmt', $recordedProductionIds[0], 'input');
        $restored->discardAttempt('lexical failure');
        $restored->beginAttempt(1);
        $restored->record(0, null, null, 'stmt', $recordedProductionIds[1], 'input');
        $restored->commitAttempt('sql-hash');
        $restored->endGeneration();
        self::assertSame(2, $restored->snapshot()['current']['reached']);
        self::assertSame(2, $restored->snapshot()['cumulative']['reached']);
        unset($restored);
        (new Filesystem())->remove($directory);
    }

    public function testResetKeepsFlushedHistoryAndClearsTraceAndCurrentCounters(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-coverage-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($directory);
        $coverage = new GrammarCoverage($directory);
        $coverage->register(
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
        $recordedProductionIds = $coverage->inventory()->denominator;
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[0], 'input');
        $coverage->discardAttempt('lexical failure');
        $coverage->beginAttempt(1);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[1], 'input');
        $coverage->commitAttempt('sql-hash');
        $coverage->endGeneration();
        $coverage->flush();
        $coverage->reset();
        self::assertSame(2, $coverage->snapshot()['cumulative']['reached']);
        self::assertSame(0, $coverage->snapshot()['current']['reached']);
        self::assertSame(0, $coverage->snapshot()['checkpoint']['generationsObservedInRun']);
        self::assertNull($coverage->lastGeneration());
        unset($coverage);
        (new Filesystem())->remove($directory);
    }

    public function testMergeIsIdempotentAndDoesNotCopyHistoricalCounters(): void
    {
        $previous = new GrammarCoverage(null);
        $previous->register(
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
        $recordedProductionIds = $previous->inventory()->denominator;
        $previous->beginGeneration('stmt', []);
        $previous->beginAttempt(0);
        $previous->record(0, null, null, 'stmt', $recordedProductionIds[0], 'input');
        $previous->discardAttempt('lexical failure');
        $previous->beginAttempt(1);
        $previous->record(0, null, null, 'stmt', $recordedProductionIds[1], 'input');
        $previous->commitAttempt('sql-hash');
        $previous->endGeneration();
        $coverage = new GrammarCoverage(null);
        $coverage->register(
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
        $coverage->merge($previous->snapshot());
        $coverage->merge($previous->snapshot());
        self::assertSame(2, $coverage->snapshot()['cumulative']['reached']);
        self::assertSame(0, $coverage->snapshot()['current']['reached']);
        self::assertSame(0, $coverage->snapshot()['checkpoint']['generationsObservedInRun']);
    }

    public function testMergeRejectsChangedImplementationRevisions(): void
    {
        $coverage = new GrammarCoverage(null);
        $coverage->register(
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
        $other = new GrammarCoverage(null);
        $other->register(
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
            'changed',
        );
        $this->expectException(CoverageException::class);
        $coverage->merge($other->snapshot());
    }

    public function testRegisterLeavesOldHistoryWhenTheImplementationChanges(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-coverage-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($directory);
        $coverage = new GrammarCoverage($directory);
        $coverage->register(
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
        $recordedProductionIds = $coverage->inventory()->denominator;
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[0], 'input');
        $coverage->discardAttempt('lexical failure');
        $coverage->beginAttempt(1);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[1], 'input');
        $coverage->commitAttempt('sql-hash');
        $coverage->endGeneration();
        $coverage->flush();
        unset($coverage);
        $changed = new GrammarCoverage($directory);
        $changed->register(
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
            'new-revision',
        );
        self::assertSame(0, $changed->snapshot()['cumulative']['reached']);
        $recordedProductionIds = $changed->inventory()->denominator;
        $changed->beginGeneration('stmt', []);
        $changed->beginAttempt(0);
        $changed->record(0, null, null, 'stmt', $recordedProductionIds[0], 'input');
        $changed->discardAttempt('lexical failure');
        $changed->beginAttempt(1);
        $changed->record(0, null, null, 'stmt', $recordedProductionIds[1], 'input');
        $changed->commitAttempt('sql-hash');
        $changed->endGeneration();
        $changed->flush();
        $files = glob($directory . '/*.json');
        self::assertNotFalse($files);
        self::assertCount(2, $files);
        unset($changed);
        (new Filesystem())->remove($directory);
    }

    public function testInventoryRequiresProviderRegistration(): void
    {
        $this->expectException(CoverageException::class);
        (new GrammarCoverage())->inventory();
    }

    public function testBeginGenerationReplacesThePreviousSuccessfulTrace(): void
    {
        $coverage = new GrammarCoverage(null);
        $coverage->register(
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
        $recordedProductionIds = $coverage->inventory()->denominator;
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[0], 'input');
        $coverage->discardAttempt('lexical failure');
        $coverage->beginAttempt(1);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[1], 'input');
        $coverage->commitAttempt('sql-hash');
        $coverage->endGeneration();
        $coverage->beginGeneration('missing', []);
        $coverage->beginAttempt(0);
        $coverage->endGeneration();
        self::assertNotNull($coverage->lastGeneration());
        self::assertSame('failed', $coverage->lastGeneration()['status']);
        self::assertSame(2, $coverage->lastGeneration()['generationId']);
        self::assertSame([], $coverage->lastGeneration()['reachedIds']);
    }

    public function testDiscardAttemptPreservesFailedReachability(): void
    {
        $coverage = new GrammarCoverage(null);
        $coverage->register(
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
        $recordedProductionIds = $coverage->inventory()->denominator;
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[0], 'input');
        $coverage->discardAttempt('lexical failure');
        $coverage->beginAttempt(1);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[1], 'input');
        $coverage->commitAttempt('sql-hash');
        $coverage->endGeneration();
        self::assertNotNull($coverage->lastGeneration());
        self::assertSame('discarded', $coverage->lastGeneration()['attempts'][0]['status']);
        self::assertSame('lexical failure', $coverage->lastGeneration()['attempts'][0]['error']);
    }

    public function testRecordIncludesOccurrenceParentsAndOriginalPositions(): void
    {
        $coverage = new GrammarCoverage(null);
        $coverage->register(
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
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->record(3, 1, 2, 'expr', $coverage->inventory()->denominator[2], 'input');
        self::assertNotNull($coverage->lastGeneration());
        self::assertSame(1, $coverage->lastGeneration()['attempts'][0]['events'][0]['parentNodeId']);
        self::assertSame(2, $coverage->lastGeneration()['attempts'][0]['events'][0]['rhsPosition']);
    }

    public function testCommitAttemptAndEndGenerationCommitOnlySuccessfulPaths(): void
    {
        $coverage = new GrammarCoverage(null);
        $coverage->register(
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
        $recordedProductionIds = $coverage->inventory()->denominator;
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[0], 'input');
        $coverage->discardAttempt('lexical failure');
        $coverage->beginAttempt(1);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[1], 'input');
        $coverage->commitAttempt('sql-hash');
        $coverage->endGeneration();
        self::assertNotNull($coverage->lastGeneration());
        self::assertSame('success', $coverage->lastGeneration()['status']);
        self::assertSame('sql-hash', $coverage->lastGeneration()['attempts'][1]['sqlHash']);
        self::assertFalse($coverage->snapshot()['checkpoint']['generationInProgress']);
    }

    public function testLastGenerationStartsEmptyWithoutStorage(): void
    {
        $coverage = new GrammarCoverage(null);
        $coverage->register(
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
        self::assertNull($coverage->lastGeneration());
        $coverage->flush();
        self::assertSame(0, $coverage->snapshot()['cumulative']['reached']);
    }

    public function testBeginAttemptRetainsAnEmptyAttemptBeforeAnyProductionIsSelected(): void
    {
        $coverage = new GrammarCoverage(null);
        $coverage->register(
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
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        self::assertNotNull($coverage->lastGeneration());
        self::assertSame([], $coverage->lastGeneration()['attempts'][0]['events']);
    }

    public function testEndGenerationPreservesFailureBeforeAnyAttemptWasOpened(): void
    {
        $coverage = new GrammarCoverage(null);
        $coverage->register(
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
        $coverage->beginGeneration('stmt', []);
        $coverage->endGeneration();
        self::assertNotNull($coverage->lastGeneration());
        self::assertSame('failed', $coverage->lastGeneration()['status']);
        self::assertSame([], $coverage->lastGeneration()['attempts']);
    }

    /**
     * @throws RuntimeException
     */

    public function testRestoreRejectsCorruptHistoryOnEveryRegistrationAttemptWithoutOverwritingIt(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-coverage-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($directory);
        $grammar = (new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('SELECT')])])]))->identified();
        $inventory = new GrammarCoverageInventory($grammar, 'stmt', 'test-v1');
        $coverage = new GrammarCoverage($directory);
        $coverage->register($inventory, 'revision-a');
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->record(0, null, null, 'stmt', $inventory->denominator[0], 'input');
        $coverage->commitAttempt('sql-hash');
        $coverage->endGeneration();
        $coverage->flush();
        $files = glob($directory . '/*.json');
        self::assertIsArray($files);
        self::assertCount(1, $files);
        unset($coverage);
        file_put_contents($files[0], '{broken');
        $coverage = new GrammarCoverage($directory);
        try {
            try {
                $coverage->register($inventory, 'revision-a');
                self::fail('Corrupt snapshot was accepted.');
            } catch (CoverageException $failure) {
                self::assertNotSame('', $failure->getMessage());
            }
            try {
                $coverage->register($inventory, 'revision-a');
                self::fail('Corrupt snapshot was accepted on the second registration.');
            } catch (CoverageException $failure) {
                self::assertNotSame('', $failure->getMessage());
            }
            self::assertSame('{broken', file_get_contents($files[0]));
        } finally {
            unset($coverage);
            (new Filesystem())->remove($directory);
        }
    }

    public function testSnapshotPublishesTheRegisteredInventoryAndCurrentRunIdentity(): void
    {
        $coverage = new GrammarCoverage(null);
        $coverage->register(
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
        $inventory = $coverage->inventory();
        $snapshot = $coverage->snapshot();
        self::assertSame(1, $snapshot['formatVersion']);
        self::assertSame($inventory->fingerprint, $snapshot['grammarFingerprint']);
        self::assertSame($inventory->digest, $snapshot['inventoryDigest']);
        self::assertSame('revision-a', $snapshot['generatorRevision']);
        self::assertSame('stmt', $snapshot['root']);
        self::assertSame($inventory->denominator, $snapshot['denominatorIds']);
        self::assertSame($inventory->entries, $snapshot['inventory']);
        self::assertSame([], $snapshot['adaptations']);
        self::assertNull($snapshot['restoredCheckpoint']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $snapshot['checkpoint']['runId']);
        $coverage->beginGeneration('expr', ['budget' => 7]);
        self::assertTrue($coverage->snapshot()['checkpoint']['generationInProgress']);
        self::assertSame($snapshot['checkpoint']['runId'], $coverage->snapshot()['checkpoint']['runId']);
        $coverage->reset();
        self::assertFalse($coverage->snapshot()['checkpoint']['generationInProgress']);
    }

    public function testReadingAMemorySnapshotDoesNotSaveUnflushedObservations(): void
    {
        $coverage = new GrammarCoverage(null);
        $coverage->register(
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
        $recordedProductionIds = $coverage->inventory()->denominator;
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[0], 'input');
        $coverage->discardAttempt('lexical failure');
        $coverage->beginAttempt(1);
        $coverage->record(0, null, null, 'stmt', $recordedProductionIds[1], 'input');
        $coverage->commitAttempt('sql-hash');
        $coverage->endGeneration();
        self::assertSame(2, $coverage->snapshot()['cumulative']['reached']);
        $coverage->flush();
        $coverage->reset();
        self::assertSame(0, $coverage->snapshot()['cumulative']['reached']);
        self::assertSame(0, $coverage->snapshot()['cumulative']['emitted']);
    }

    public function testHooksWithoutAGenerationKeepTheTraceEmpty(): void
    {
        $coverage = new GrammarCoverage(null);
        $coverage->register(
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
        $coverage->restore();
        $coverage->beginAttempt(0);
        $coverage->discardAttempt('no active generation');
        $coverage->commitAttempt('hash');
        $coverage->endGeneration();
        $coverage->record(0, null, null, 'stmt', $coverage->inventory()->denominator[0], 'input');
        self::assertNull($coverage->lastGeneration());
        self::assertSame(1, $coverage->snapshot()['current']['reached']);
        self::assertSame(0, $coverage->snapshot()['current']['emitted']);
    }

    public function testFailedReachabilityAndLaterEmissionAreSavedAsSeparateDiscoveries(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-coverage-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($directory);
        $coverage = new GrammarCoverage($directory);
        $coverage->register(
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
        $path = $directory . '/' . hash('sha256', '1:' . $coverage->inventory()->fingerprint . ':revision-a') . '.json';
        $id = $coverage->inventory()->denominator[0];
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->record(0, null, null, 'stmt', $id, 'input');
        $coverage->discardAttempt('failed');
        $coverage->endGeneration();
        $coverage->flush();
        self::assertFileExists($path);
        $first = file_get_contents($path);
        self::assertNotFalse($first);
        self::assertStringContainsString("\n", $first);
        unset($coverage);
        $restored = new GrammarCoverage($directory);
        $restored->register(
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
        self::assertSame(1, $restored->snapshot()['cumulative']['reached']);
        self::assertSame(0, $restored->snapshot()['cumulative']['emitted']);
        self::assertNotNull($restored->snapshot()['restoredCheckpoint']);
        $restored->flush();
        self::assertSame($first, file_get_contents($path));
        $restored->beginGeneration('stmt', []);
        $restored->beginAttempt(0);
        $restored->record(0, null, null, 'stmt', $id, 'input');
        $restored->flush();
        self::assertSame($first, file_get_contents($path));
        $restored->commitAttempt('success');
        $restored->endGeneration();
        $restored->flush();
        $emitted = file_get_contents($path);
        self::assertNotSame($first, $emitted);
        $restored->beginGeneration('stmt', []);
        $restored->beginAttempt(0);
        $restored->record(0, null, null, 'stmt', $id, 'input');
        $restored->commitAttempt('same production');
        $restored->endGeneration();
        $restored->flush();
        self::assertSame($emitted, file_get_contents($path));
        unset($restored);
        $final = new GrammarCoverage($directory);
        $final->register(
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
        self::assertSame(1, $final->snapshot()['cumulative']['emitted']);
        unset($final);
        (new Filesystem())->remove($directory);
    }

    public function testMergePreservesDisjointHistoryAndDoesNotResaveIdenticalHistory(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-coverage-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($directory);
        $coverage = new GrammarCoverage($directory);
        $coverage->register(
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
        $path = $directory . '/' . hash('sha256', '1:' . $coverage->inventory()->fingerprint . ':revision-a') . '.json';
        $other = new GrammarCoverage(null);
        $other->register(
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
        $recordedProductionIds = $other->inventory()->denominator;
        $other->beginGeneration('stmt', []);
        $other->beginAttempt(0);
        $other->record(0, null, null, 'stmt', $recordedProductionIds[0], 'input');
        $other->discardAttempt('lexical failure');
        $other->beginAttempt(1);
        $other->record(0, null, null, 'stmt', $recordedProductionIds[1], 'input');
        $other->commitAttempt('sql-hash');
        $other->endGeneration();
        $coverage->merge($other->snapshot());
        $coverage->flush();
        self::assertFileExists($path);
        $first = file_get_contents($path);
        $coverage->beginGeneration('stmt', []);
        $coverage->merge($other->snapshot());
        $coverage->flush();
        self::assertSame($first, file_get_contents($path));
        $other->reset();
        $id = $other->inventory()->denominator[2];
        $other->beginGeneration('expr', []);
        $other->beginAttempt(0);
        $other->record(0, null, null, 'expr', $id, 'input');
        $other->commitAttempt('other');
        $other->endGeneration();
        $coverage->merge($other->snapshot());
        self::assertSame(3, $coverage->snapshot()['cumulative']['reached']);
        self::assertSame(2, $coverage->snapshot()['cumulative']['emitted']);
        $coverage->flush();
        unset($coverage);
        $restored = new GrammarCoverage($directory);
        $restored->register(
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
        self::assertSame(3, $restored->snapshot()['cumulative']['reached']);
        self::assertSame(2, $restored->snapshot()['cumulative']['emitted']);
        unset($restored);
        (new Filesystem())->remove($directory);
    }

    public function testRecordSequenceKeepsTransformedSelectionsOutOfPreservedOutputCoverage(): void
    {
        $coverage = new GrammarCoverage(null);
        $coverage->register(
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
        $grammar = (new Grammar(
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
        ))->identified();
        $trace = new DerivationTrace('stmt');
        $trace->expand(0, $grammar->ruleMap['stmt']->alternatives[0], 0);
        $trace->expand(1, $grammar->ruleMap['expr']->alternatives[0], 0);
        $sequence = $trace->terminals();
        $changed = $sequence->replace(0, 1, [$sequence->terminals[0]->replaced('OTHER', 'fixture')], 'fixture');
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->recordSequence($changed);
        $coverage->commitAttempt('sql-hash', (new SequenceObservation())->preserved($changed));
        $coverage->endGeneration();
        self::assertSame(2, $coverage->snapshot()['current']['reached']);
        self::assertSame(1, $coverage->snapshot()['current']['emitted']);
        $trace = $coverage->lastGeneration();
        self::assertNotNull($trace);
        self::assertSame(['fixture'], $trace['rewrites']);
    }

    public function testRecordOutputExposesCandidateAndBoundaryDecisions(): void
    {
        $coverage = new GrammarCoverage(null);
        $coverage->register(
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
        $coverage->beginGeneration('stmt', []);
        $coverage->recordOutput(
            (new ReverseLexemeGenerator(
                new FixedLexemeGenerator('SELECT', 'fixture', 'fixture-literal'),
                new CandidateResolver(new CombinedSpacingRule()),
                'fixture',
            ))->generate(TerminalSequence::fromNames(['SELECT']), null, static fn (int $count): int => 0),
        );
        $trace = $coverage->lastGeneration();
        self::assertNotNull($trace);
        self::assertCount(1, $trace['lexicalEvents']);
        self::assertSame('', $trace['spacingEvents'][0]['separator']);
        self::assertSame([], $trace['spacingEvents'][0]['rules']);
    }

    public function testEndGenerationSavesUnsavedDiscoveriesEveryHundredGenerations(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-coverage-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($directory);
        $coverage = new GrammarCoverage($directory);
        $coverage->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('SELECT')]), new Production([new Terminal('DELETE')])])],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        $recordedProductionIds = $coverage->inventory()->denominator;
        $generate = static function (int $generation) use ($coverage, $recordedProductionIds): void {
            $coverage->beginGeneration('stmt', []);
            $coverage->beginAttempt(0);
            $coverage->record(0, null, null, 'stmt', $recordedProductionIds[0], 'input');
            $coverage->commitAttempt('sql-hash-' . $generation);
            $coverage->endGeneration();
        };
        array_map($generate, range(1, GrammarCoverage::FLUSH_INTERVAL - 1));
        self::assertSame([], glob($directory . '/*.json'));
        $generate(GrammarCoverage::FLUSH_INTERVAL);
        $snapshots = glob($directory . '/*.json');
        self::assertNotFalse($snapshots);
        self::assertCount(1, $snapshots);
        unset($coverage, $generate);
        $restored = new GrammarCoverage($directory);
        $restored->register(
            new GrammarCoverageInventory(
                (new Grammar(
                    'stmt',
                    ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('SELECT')]), new Production([new Terminal('DELETE')])])],
                ))->identified(),
                'stmt',
                'test-v1',
            ),
            'revision-a',
        );
        self::assertSame(1, $restored->snapshot()['cumulative']['reached']);
        unset($restored);
        (new Filesystem())->remove($directory);
    }
}
