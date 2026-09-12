<?php

declare (strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage;

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
use SqlFaker\Coverage\SnapshotValidation;
use SqlFaker\Generation\Choice\ByteChoices;
use SqlFaker\Generation\Derivation\CompletionCosts;
use SqlFaker\Generation\Derivation\DerivationNode;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(SnapshotValidation::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(GrammarCoverageInventory::class)]
#[UsesClass(CoverageException::class)]
#[UsesClass(GrammarCoverage::class)]
#[UsesClass(GeneratorRevision::class)]
#[UsesClass(CoverageSnapshotStore::class)]
#[UsesClass(GenerationTrace::class)]
#[UsesClass(CoverageSets::class)]
#[UsesClass(ByteChoices::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(DerivationNode::class)]
#[UsesClass(LexicalObservation::class)]
final class SnapshotValidationTest extends TestCase
{
    /**
     * @throws JsonException
     */

    public function testDecodeRestoresOnlyWellFormedSnapshotData(): void
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
        $snapshot = $grammarCoverage->snapshot();
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
        $snapshot = $coverage->snapshot();
        $snapshot['cumulative']['reachedIds'] = ['unknown'];
        $this->expectException(CoverageException::class);
        (new SnapshotValidation())->compatible($snapshot, $coverage->snapshot(), $coverage->inventory());
    }

    public function testCompatibleRejectsAnUntrustedInventoryDigest(): void
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
        $snapshot = $coverage->snapshot();
        $snapshot['inventoryDigest'] = 'wrong';
        $this->expectException(CoverageException::class);
        (new SnapshotValidation())->compatible($snapshot, $coverage->snapshot(), $coverage->inventory());
    }

    public function testCompatibleRejectsEmittedProductionsThatWereNeverReached(): void
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
        $snapshot = $coverage->snapshot();
        $snapshot['cumulative']['emittedIds'] = [$coverage->inventory()->denominator[0]];
        $this->expectException(CoverageException::class);
        (new SnapshotValidation())->compatible($snapshot, $coverage->snapshot(), $coverage->inventory());
    }

    public function testCheckpointRequiresTypedDiagnosticFields(): void
    {
        $validator = new SnapshotValidation();
        self::assertTrue(
            $validator->checkpoint((object) ['savedAt' => 'time', 'runId' => 'run', 'generationsObservedInRun' => 1, 'generationInProgress' => false]),
        );
        self::assertFalse($validator->checkpoint((object) ['savedAt' => 'time']));
    }

    /**
     * @return list<array{string}>
     * @throws JsonException
     */

    public static function providerMalformedHistories(): array
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
        $base = $grammarCoverage->snapshot();
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
    #[DataProvider('providerMalformedHistories')]

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
    #[DataProvider('providerIdentityFields')]

    public function testCompatibleRequiresEveryIdentityComponent(string $field): void
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
        $snapshot = $coverage->snapshot();
        $different = $snapshot;
        $different[$field] = 'different';
        $this->expectException(CoverageException::class);
        $this->expectExceptionMessage('Incompatible coverage snapshot: ' . $field);
        (new SnapshotValidation())->compatible($different, $snapshot, $coverage->inventory());
    }
}
