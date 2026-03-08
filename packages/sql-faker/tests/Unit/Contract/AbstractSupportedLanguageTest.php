<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\AbstractSupportedLanguage;
use SqlFaker\Contract\FamilyDefinition;
use SqlFaker\Contract\FamilyRequest;
use SqlFaker\Contract\GrammarSnapshot;

#[CoversClass(AbstractSupportedLanguage::class)]
final class AbstractSupportedLanguageTest extends TestCase
{
    public function testGrammarSnapshotAndFamilyCatalogAreCached(): void
    {
        $language = new class () extends AbstractSupportedLanguage {
            public int $familyBuilds = 0;
            public int $snapshotBuilds = 0;

            public function dialect(): string
            {
                return 'stub';
            }

            public function generateWitness(FamilyRequest $request): \SqlFaker\Contract\SqlWitness
            {
                return $this->searchWitness(
                    $request->familyId,
                    $request->parameters,
                    static fn (): string => 'SELECT 1',
                );
            }

            protected function buildFamilies(): array
            {
                $this->familyBuilds++;

                return [
                    new FamilyDefinition('stub.family', 'stub', 'contract', ['stmt']),
                ];
            }

            protected function buildGrammarSnapshot(): GrammarSnapshot
            {
                $this->snapshotBuilds++;

                return new GrammarSnapshot('stub', 'stmt', ['stmt'], [], ['stub.family' => ['stmt']]);
            }

            protected function seed(int $seed): void
            {
            }
        };

        $firstSnapshot = $language->grammarSnapshot();
        $secondSnapshot = $language->grammarSnapshot();
        $firstCatalog = $language->familyCatalog();
        $secondCatalog = $language->familyCatalog();

        self::assertSame($firstSnapshot, $secondSnapshot);
        self::assertSame($firstCatalog, $secondCatalog);
        self::assertSame(1, $language->snapshotBuilds);
        self::assertSame(1, $language->familyBuilds);
    }

    public function testGenerateWitnessUsesValidatedParametersAndSearchLoop(): void
    {
        $language = new class () extends AbstractSupportedLanguage {
            public int $seedValue = 0;

            public function dialect(): string
            {
                return 'stub';
            }

            public function generateWitness(FamilyRequest $request): \SqlFaker\Contract\SqlWitness
            {
                $this->assertFamilyParameters($request);

                return $this->searchWitness(
                    $request->familyId,
                    $request->parameters,
                    fn (array $parameters): string => sprintf('SELECT %d, %d', $this->seedValue, (int) $parameters['arity']),
                    fn (): bool => $this->seedValue === 3,
                    fn (): array => ['seed_seen' => $this->seedValue],
                    4,
                );
            }

            protected function buildFamilies(): array
            {
                return [
                    new FamilyDefinition('stub.family', 'stub', 'contract', ['stmt'], ['arity'], ['seed_seen']),
                ];
            }

            protected function buildGrammarSnapshot(): GrammarSnapshot
            {
                return new GrammarSnapshot('stub', 'stmt', ['stmt'], [], ['stub.family' => ['stmt']]);
            }

            protected function seed(int $seed): void
            {
                $this->seedValue = $seed;
            }
        };

        $witness = $language->generateWitness(new FamilyRequest('stub.family', ['arity' => 2]));

        self::assertSame(3, $witness->seed);
        self::assertSame(['arity' => 2], $witness->parameters);
        self::assertSame(['seed_seen' => 3], $witness->properties);
        self::assertSame('SELECT 3, 2', $witness->sql);
    }

    public function testGenerateWitnessRejectsUnknownAndMissingParameters(): void
    {
        $language = new class () extends AbstractSupportedLanguage {
            public function dialect(): string
            {
                return 'stub';
            }

            public function generateWitness(FamilyRequest $request): \SqlFaker\Contract\SqlWitness
            {
                $this->assertFamilyParameters($request);

                throw new LogicException('unreachable');
            }

            protected function buildFamilies(): array
            {
                return [
                    new FamilyDefinition('stub.family', 'stub', 'contract', ['stmt'], ['arity']),
                ];
            }

            protected function buildGrammarSnapshot(): GrammarSnapshot
            {
                return new GrammarSnapshot('stub', 'stmt', ['stmt'], [], ['stub.family' => ['stmt']]);
            }

            protected function seed(int $seed): void
            {
            }
        };

        try {
            $language->generateWitness(new FamilyRequest('stub.family', ['extra' => 1]));
            self::fail();
        } catch (LogicException $e) {
            self::assertStringContainsString('Unknown parameters', $e->getMessage());
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Missing required parameter arity');
        $language->generateWitness(new FamilyRequest('stub.family'));
    }
}
