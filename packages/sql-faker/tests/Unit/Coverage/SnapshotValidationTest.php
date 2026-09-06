<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\SnapshotValidation;
use Tests\Fixtures\SqlFaker\CoverageFixture;

#[CoversClass(SnapshotValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Grammar::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Production::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\ProductionRule::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Terminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GrammarCoverageInventory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(CoverageException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GrammarCoverage::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GeneratorRevision::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageSnapshotStore::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\GenerationTrace::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Coverage\CoverageSets::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Choice\ChoiceSource::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Choice\ByteChoices::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Grammar\Derivation\DerivationNode::class)]
final class SnapshotValidationTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testDecodeRestoresOnlyWellFormedSnapshotData(): void
    {
        $snapshot = CoverageFixture::coverage()->snapshot();
        self::assertSame($snapshot, (new SnapshotValidation())->decode(json_encode($snapshot, JSON_THROW_ON_ERROR)));
    }

    public function testDecodeRejectsCorruptJsonInsteadOfStartingEmptyHistory(): void
    {
        $this->expectException(CoverageException::class);
        (new SnapshotValidation())->decode('{"broken":');
    }

    public function testDecodeRejectsAnUnknownFormatVersion(): void
    {
        $this->expectException(CoverageException::class);
        (new SnapshotValidation())->decode('{"formatVersion":2}');
    }

    public function testCompatibleRejectsUnknownProductionIds(): void
    {
        $coverage = CoverageFixture::coverage();
        $snapshot = $coverage->snapshot();
        $snapshot['cumulative']['reachedIds'] = ['unknown'];
        $this->expectException(CoverageException::class);
        (new SnapshotValidation())->compatible($snapshot, $coverage->snapshot(), $coverage->inventory());
    }

    public function testCompatibleRejectsAnUntrustedInventoryDigest(): void
    {
        $coverage = CoverageFixture::coverage();
        $snapshot = $coverage->snapshot();
        $snapshot['inventoryDigest'] = 'wrong';
        $this->expectException(CoverageException::class);
        (new SnapshotValidation())->compatible($snapshot, $coverage->snapshot(), $coverage->inventory());
    }

    public function testCompatibleRejectsEmittedProductionsThatWereNeverReached(): void
    {
        $coverage = CoverageFixture::coverage();
        $snapshot = $coverage->snapshot();
        $snapshot['cumulative']['emittedIds'] = [$coverage->inventory()->denominator[0]];
        $this->expectException(CoverageException::class);
        (new SnapshotValidation())->compatible($snapshot, $coverage->snapshot(), $coverage->inventory());
    }

    public function testCheckpointRequiresTypedDiagnosticFields(): void
    {
        $validator = new SnapshotValidation();
        self::assertTrue($validator->checkpoint((object) ['savedAt' => 'time', 'runId' => 'run', 'generationsObservedInRun' => 1, 'generationInProgress' => false]));
        self::assertFalse($validator->checkpoint((object) ['savedAt' => 'time']));
    }

    /**
     * @return list<array{string}>
     * @throws JsonException
     */
    public static function providerMalformedHistories(): array
    {
        $base = CoverageFixture::coverage()->snapshot();
        $cases = [null, [], ['formatVersion' => '1'], ['formatVersion' => 1]];
        foreach (['grammarFingerprint', 'generatorRevision', 'root', 'inventoryDigest', 'cumulative', 'checkpoint'] as $field) {
            $missing = $base;
            unset($missing[$field]);
            $cases[] = $missing;
            $cases[] = array_replace($base, [$field => 7]);
        }
        foreach (['reachedIds', 'emittedIds'] as $field) {
            foreach ([null, [false], ['name' => 'id']] as $invalid) {
                $cases[] = array_replace($base, ['cumulative' => array_replace($base['cumulative'], [$field => $invalid])]);
            }
        }
        foreach (['savedAt' => 1, 'runId' => false, 'generationsObservedInRun' => '2', 'generationInProgress' => 0] as $field => $invalid) {
            $cases[] = array_replace($base, ['checkpoint' => array_replace($base['checkpoint'], [$field => $invalid])]);
        }
        return array_values(array_map(static fn ($case): array => [json_encode($case, JSON_THROW_ON_ERROR)], $cases));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerMalformedHistories')]
    public function testDecodeRejectsMalformedHistoryFields(string $json): void
    {
        $this->expectException(CoverageException::class);
        (new SnapshotValidation())->decode($json);
    }

    /**
     * @return list<array{'grammarFingerprint'|'generatorRevision'|'root'|'inventoryDigest'}>
     */
    public static function providerIdentityFields(): array
    {
        return [['grammarFingerprint'], ['generatorRevision'], ['root'], ['inventoryDigest']];
    }

    /**
     * @param 'grammarFingerprint'|'generatorRevision'|'root'|'inventoryDigest' $field
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIdentityFields')]
    public function testCompatibleRequiresEveryIdentityComponent(string $field): void
    {
        $coverage = CoverageFixture::coverage();
        $snapshot = $coverage->snapshot();
        $different = $snapshot;
        $different[$field] = 'different';
        $this->expectException(CoverageException::class);
        $this->expectExceptionMessage('Incompatible coverage snapshot: ' . $field);
        (new SnapshotValidation())->compatible($different, $snapshot, $coverage->inventory());
    }

}
