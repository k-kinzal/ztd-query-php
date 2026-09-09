<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage\Verification;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Coverage\Verification\VerificationCoverage;
use SqlFaker\Coverage\Verification\VerificationEvent;
use SqlFaker\Coverage\Verification\VerificationResult;
use Tests\Fixtures\SqlFaker\CoverageFixture;
use Tests\Fixtures\SqlFaker\VerificationFixture;

#[CoversClass(VerificationCoverage::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(GrammarCoverage::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(VerificationEvent::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(VerificationResult::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\Verification\SqlContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\Verification\VerificationWitnessStore::class)]
final class VerificationCoverageTest extends TestCase
{
    public function testRecordSeparatesAcceptedInconclusiveAndUnsupportedObservations(): void
    {
        $grammar = VerificationFixture::coverage();
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
        $coverage = new VerificationCoverage(VerificationFixture::coverage(), 'oracle-a', []);
        $this->expectException(CoverageException::class);
        $coverage->record('SELECT 2', 'input', new VerificationResult('accepted'));
    }

    public function testRecordRejectsAnObservationBeforeGeneration(): void
    {
        $coverage = new VerificationCoverage(CoverageFixture::coverage(), 'oracle-a', []);
        $this->expectException(CoverageException::class);
        $coverage->record('SELECT 1', 'input', new VerificationResult('accepted'));
    }

    public function testSnapshotKeepsTheOriginalDenominatorAndFirstWitness(): void
    {
        $grammar = VerificationFixture::coverage();
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
        $directory = CoverageFixture::directory();
        $grammar = VerificationFixture::coverage();
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
        CoverageFixture::remove($directory);
    }
    /**
     * @throws JsonException
     */
    public function testMergeUnionsCompatibleWitnessesIdempotently(): void
    {
        $source = new VerificationCoverage(VerificationFixture::coverage(), 'oracle-a', []);
        $source->record('SELECT 1', 'input', new VerificationResult('accepted'));
        $destination = new VerificationCoverage(VerificationFixture::coverage(), 'oracle-a', []);
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
        $coverage = new VerificationCoverage(VerificationFixture::coverage(), 'oracle-a', []);
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
        $source = new VerificationCoverage(VerificationFixture::coverage(), 'oracle-a', []);
        $source->record('SELECT 1', 'input', new VerificationResult('accepted'));
        $snapshot = $source->snapshot();
        yield [json_encode([...$snapshot, 'formatVersion' => 9], JSON_THROW_ON_ERROR)];
        yield [json_encode([...$snapshot, 'identity' => [...$snapshot['identity'], 'oracleRevision' => 'old']], JSON_THROW_ON_ERROR)];
        yield [json_encode([...$snapshot, 'witnesses' => [[...$snapshot['witnesses'][0], 'id' => 'unknown']]], JSON_THROW_ON_ERROR)];
        yield [json_encode([...$snapshot, 'witnesses' => [[...$snapshot['witnesses'][0], 'sqlHash' => 'invalid']]], JSON_THROW_ON_ERROR)];
    }

    public function testRecordAssociatesCheckedWrappersWithTheOriginalGeneratedFragment(): void
    {
        $grammar = VerificationFixture::coverage('1');
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
        $directory = CoverageFixture::directory();
        $grammar = VerificationFixture::lexicalCoverage();
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
        CoverageFixture::remove($directory);
    }
}
