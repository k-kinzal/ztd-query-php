<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\GrammarCoverage;
use Tests\Fixtures\SqlFaker\CoverageFixture;

#[CoversClass(GrammarCoverage::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Grammar::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Production::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\ProductionRule::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Terminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GrammarCoverageInventory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(CoverageException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GeneratorRevision::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageSnapshotStore::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\SnapshotValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GenerationTrace::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageSets::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Choice\ByteChoices::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\DerivationNode::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\SequenceObservation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\DerivationTrace::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeInput::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Output\CandidateResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Output\ResolvedOutput::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\LexicalObservation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Value\ValueChoices::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
final class GrammarCoverageTest extends TestCase
{
    public function testSnapshotSeparatesReachedFromEmittedAndKeepsTheFullDenominator(): void
    {
        $coverage = CoverageFixture::coverage();
        CoverageFixture::record($coverage);
        self::assertSame(5, $coverage->snapshot()['current']['total']);
        self::assertSame(2, $coverage->snapshot()['current']['reached']);
        self::assertSame(1, $coverage->snapshot()['current']['emitted']);
        self::assertSame(3, count($coverage->snapshot()['current']['notReachedIds']));
        self::assertSame(0.4, $coverage->snapshot()['current']['reachedRate']);
    }

    public function testFlushRestoresOnlyCumulativeHistoryAndPreservesCurrentObservations(): void
    {
        $directory = CoverageFixture::directory();
        $coverage = CoverageFixture::coverage($directory);
        CoverageFixture::record($coverage);
        $coverage->flush();
        $coverage->flush();
        self::assertSame(2, $coverage->snapshot()['current']['reached']);
        unset($coverage);
        $restored = CoverageFixture::coverage($directory);
        self::assertSame(2, $restored->snapshot()['cumulative']['reached']);
        self::assertSame(0, $restored->snapshot()['current']['reached']);
        self::assertNull($restored->lastGeneration());
        CoverageFixture::record($restored);
        self::assertSame(2, $restored->snapshot()['current']['reached']);
        self::assertSame(2, $restored->snapshot()['cumulative']['reached']);
        unset($restored);
        CoverageFixture::remove($directory);
    }

    public function testResetKeepsFlushedHistoryAndClearsTraceAndCurrentCounters(): void
    {
        $directory = CoverageFixture::directory();
        $coverage = CoverageFixture::coverage($directory);
        CoverageFixture::record($coverage);
        $coverage->flush();
        $coverage->reset();
        self::assertSame(2, $coverage->snapshot()['cumulative']['reached']);
        self::assertSame(0, $coverage->snapshot()['current']['reached']);
        self::assertSame(0, $coverage->snapshot()['checkpoint']['generationsObservedInRun']);
        self::assertNull($coverage->lastGeneration());
        unset($coverage);
        CoverageFixture::remove($directory);
    }

    public function testMergeIsIdempotentAndDoesNotCopyHistoricalCounters(): void
    {
        $previous = CoverageFixture::coverage();
        CoverageFixture::record($previous);
        $coverage = CoverageFixture::coverage();
        $coverage->merge($previous->snapshot());
        $coverage->merge($previous->snapshot());
        self::assertSame(2, $coverage->snapshot()['cumulative']['reached']);
        self::assertSame(0, $coverage->snapshot()['current']['reached']);
        self::assertSame(0, $coverage->snapshot()['checkpoint']['generationsObservedInRun']);
    }

    public function testMergeRejectsChangedImplementationRevisions(): void
    {
        $coverage = CoverageFixture::coverage();
        $other = CoverageFixture::coverage(revision: 'changed');
        $this->expectException(CoverageException::class);
        $coverage->merge($other->snapshot());
    }

    public function testRegisterLeavesOldHistoryWhenTheImplementationChanges(): void
    {
        $directory = CoverageFixture::directory();
        $coverage = CoverageFixture::coverage($directory);
        CoverageFixture::record($coverage);
        $coverage->flush();
        unset($coverage);
        $changed = CoverageFixture::coverage($directory, 'new-revision');
        self::assertSame(0, $changed->snapshot()['cumulative']['reached']);
        CoverageFixture::record($changed);
        $changed->flush();
        $files = glob($directory . '/*.json');
        self::assertNotFalse($files);
        self::assertCount(2, $files);
        unset($changed);
        CoverageFixture::remove($directory);
    }

    public function testInventoryRequiresProviderRegistration(): void
    {
        $this->expectException(CoverageException::class);
        (new GrammarCoverage())->inventory();
    }

    public function testBeginGenerationReplacesThePreviousSuccessfulTrace(): void
    {
        $coverage = CoverageFixture::coverage();
        CoverageFixture::record($coverage);
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
        $coverage = CoverageFixture::coverage();
        CoverageFixture::record($coverage);
        self::assertNotNull($coverage->lastGeneration());
        self::assertSame('discarded', $coverage->lastGeneration()['attempts'][0]['status']);
        self::assertSame('lexical failure', $coverage->lastGeneration()['attempts'][0]['error']);
    }

    public function testRecordIncludesOccurrenceParentsAndOriginalPositions(): void
    {
        $coverage = CoverageFixture::coverage();
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->record(3, 1, 2, 'expr', $coverage->inventory()->denominator[2], 'input');
        self::assertNotNull($coverage->lastGeneration());
        self::assertSame(1, $coverage->lastGeneration()['attempts'][0]['events'][0]['parentNodeId']);
        self::assertSame(2, $coverage->lastGeneration()['attempts'][0]['events'][0]['rhsPosition']);
    }

    public function testCommitAttemptAndEndGenerationCommitOnlySuccessfulPaths(): void
    {
        $coverage = CoverageFixture::coverage();
        CoverageFixture::record($coverage);
        self::assertNotNull($coverage->lastGeneration());
        self::assertSame('success', $coverage->lastGeneration()['status']);
        self::assertSame('sql-hash', $coverage->lastGeneration()['attempts'][1]['sqlHash']);
        self::assertFalse($coverage->snapshot()['checkpoint']['generationInProgress']);
    }

    public function testLastGenerationStartsEmptyWithoutStorage(): void
    {
        $coverage = CoverageFixture::coverage();
        self::assertNull($coverage->lastGeneration());
        $coverage->flush();
        self::assertSame(0, $coverage->snapshot()['cumulative']['reached']);
    }

    public function testBeginAttemptRetainsAnEmptyAttemptBeforeAnyProductionIsSelected(): void
    {
        $coverage = CoverageFixture::coverage();
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        self::assertNotNull($coverage->lastGeneration());
        self::assertSame([], $coverage->lastGeneration()['attempts'][0]['events']);
    }

    public function testEndGenerationPreservesFailureBeforeAnyAttemptWasOpened(): void
    {
        $coverage = CoverageFixture::coverage();
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
        $result = CoverageFixture::corruptRestore();
        self::assertCount(2, $result['failures']);
        self::assertSame('{broken', $result['contents']);
    }

    public function testSnapshotPublishesTheRegisteredInventoryAndCurrentRunIdentity(): void
    {
        $coverage = CoverageFixture::coverage();
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
        $coverage = CoverageFixture::coverage();
        CoverageFixture::record($coverage);
        self::assertSame(2, $coverage->snapshot()['cumulative']['reached']);
        $coverage->flush();
        $coverage->reset();
        self::assertSame(0, $coverage->snapshot()['cumulative']['reached']);
        self::assertSame(0, $coverage->snapshot()['cumulative']['emitted']);
    }

    public function testHooksWithoutAGenerationKeepTheTraceEmpty(): void
    {
        $coverage = CoverageFixture::coverage();
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
        $directory = CoverageFixture::directory();
        $coverage = CoverageFixture::coverage($directory);
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
        $restored = CoverageFixture::coverage($directory);
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
        $final = CoverageFixture::coverage($directory);
        self::assertSame(1, $final->snapshot()['cumulative']['emitted']);
        unset($final);
        CoverageFixture::remove($directory);
    }

    public function testMergePreservesDisjointHistoryAndDoesNotResaveIdenticalHistory(): void
    {
        $directory = CoverageFixture::directory();
        $coverage = CoverageFixture::coverage($directory);
        $path = $directory . '/' . hash('sha256', '1:' . $coverage->inventory()->fingerprint . ':revision-a') . '.json';
        $other = CoverageFixture::coverage();
        CoverageFixture::record($other);
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
        $restored = CoverageFixture::coverage($directory);
        self::assertSame(3, $restored->snapshot()['cumulative']['reached']);
        self::assertSame(2, $restored->snapshot()['cumulative']['emitted']);
        unset($restored);
        CoverageFixture::remove($directory);
    }
    public function testRecordSequenceKeepsTransformedSelectionsOutOfPreservedOutputCoverage(): void
    {
        $coverage = CoverageFixture::coverage();
        $grammar = CoverageFixture::grammar();
        $trace = new \SqlFaker\Grammar\Derivation\DerivationTrace('stmt');
        $trace->expand(0, $grammar->ruleMap['stmt']->alternatives[0], 0);
        $trace->expand(1, $grammar->ruleMap['expr']->alternatives[0], 0);
        $sequence = $trace->terminals();
        $changed = $sequence->replace(0, 1, [$sequence->terminals[0]->replaced('OTHER', 'fixture')], 'fixture');
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->recordSequence($changed);
        $coverage->commitAttempt('sql-hash', (new \SqlFaker\Coverage\SequenceObservation())->preserved($changed));
        $coverage->endGeneration();
        self::assertSame(2, $coverage->snapshot()['current']['reached']);
        self::assertSame(1, $coverage->snapshot()['current']['emitted']);
        $trace = $coverage->lastGeneration();
        self::assertNotNull($trace);
        self::assertSame(['fixture'], $trace['rewrites']);
    }

    public function testRecordOutputExposesCandidateAndBoundaryDecisions(): void
    {
        $coverage = CoverageFixture::coverage();
        $coverage->beginGeneration('stmt', []);
        $coverage->recordOutput(CoverageFixture::output('SELECT'));
        $trace = $coverage->lastGeneration();
        self::assertNotNull($trace);
        self::assertCount(1, $trace['lexicalEvents']);
        self::assertSame('', $trace['spacingEvents'][0]['separator']);
        self::assertSame([], $trace['spacingEvents'][0]['rules']);
    }
}
