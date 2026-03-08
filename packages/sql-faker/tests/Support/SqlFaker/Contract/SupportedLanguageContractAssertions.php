<?php

declare(strict_types=1);

namespace Tests\Support\SqlFaker\Contract;

use LogicException;
use PHPUnit\Framework\Assert;
use SqlFaker\Contract\FamilyDefinition;
use SqlFaker\Contract\FamilyRequest;
use SqlFaker\Contract\GrammarAlternativeSnapshot;
use SqlFaker\Contract\SupportedLanguage as SupportedLanguageContract;

final class SupportedLanguageContractAssertions
{
    public static function assertGeneratesWitnessesForEveryFamily(SupportedLanguageContract $language): void
    {
        foreach ($language->familyCatalog() as $family) {
            foreach (self::parameterSetsFor($family) as $parameters) {
                $witness = $language->generateWitness(new FamilyRequest($family->id, $parameters));

                Assert::assertSame($family->id, $witness->familyId, $family->id);
                Assert::assertSame($parameters, $witness->parameters, $family->id);
                Assert::assertNotSame('', $witness->sql, $family->id);

                foreach ($family->propertyNames as $propertyName) {
                    Assert::assertArrayHasKey($propertyName, $witness->properties, $family->id . ':' . $propertyName);
                }

                if (array_key_exists('arity', $parameters)) {
                    foreach ($family->propertyNames as $propertyName) {
                        if (str_contains($propertyName, 'arity')) {
                            Assert::assertSame(
                                $parameters['arity'],
                                $witness->properties[$propertyName],
                                $family->id . ':' . $propertyName,
                            );
                        }
                    }
                }

                if (array_key_exists('schema_qualified', $parameters) && in_array('schema_qualified', $family->propertyNames, true)) {
                    Assert::assertSame(
                        $parameters['schema_qualified'],
                        $witness->properties['schema_qualified'],
                        $family->id,
                    );
                }
            }
        }
    }

    public static function contractFingerprint(SupportedLanguageContract $language): string
    {
        $snapshot = $language->grammarSnapshot();
        $rules = [];
        foreach ($snapshot->rules as $ruleName => $rule) {
            $rules[$ruleName] = array_map(
                static fn (GrammarAlternativeSnapshot $alternative): array => $alternative->sequence(),
                $rule->alternatives,
            );
        }
        ksort($rules);

        $familyAnchors = $snapshot->familyAnchors;
        ksort($familyAnchors);

        $catalog = array_map(
            static fn (FamilyDefinition $family): array => [
                'id' => $family->id,
                'anchors' => $family->anchorRules,
                'params' => $family->parameterNames,
                'props' => $family->propertyNames,
            ],
            $language->familyCatalog(),
        );
        usort(
            $catalog,
            static fn (array $left, array $right): int => $left['id'] <=> $right['id'],
        );

        $payload = [
            'dialect' => $snapshot->dialect,
            'startRule' => $snapshot->startRule,
            'entryRules' => $snapshot->entryRules,
            'familyAnchors' => $familyAnchors,
            'rules' => $rules,
            'catalog' => $catalog,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public static function witnessFingerprint(SupportedLanguageContract $language): string
    {
        $witnesses = [];

        foreach ($language->familyCatalog() as $family) {
            foreach (self::parameterSetsFor($family) as $parameters) {
                $witness = $language->generateWitness(new FamilyRequest($family->id, $parameters));
                $witnesses[] = [
                    'familyId' => $witness->familyId,
                    'seed' => $witness->seed,
                    'sql' => $witness->sql,
                    'parameters' => $witness->parameters,
                    'properties' => $witness->properties,
                ];
            }
        }

        return hash('sha256', json_encode($witnesses, JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<array<string, bool|int>>
     */
    private static function parameterSetsFor(FamilyDefinition $family): array
    {
        $parameterSets = [[]];

        foreach ($family->parameterNames as $parameterName) {
            $values = match ($parameterName) {
                'arity' => [1, 8],
                'schema_qualified' => [true, false],
                default => throw new LogicException(sprintf('Unhandled family parameter: %s', $parameterName)),
            };

            $expanded = [];
            foreach ($parameterSets as $parameterSet) {
                foreach ($values as $value) {
                    $next = $parameterSet;
                    $next[$parameterName] = $value;
                    $expanded[] = $next;
                }
            }

            $parameterSets = $expanded;
        }

        return $parameterSets;
    }
}
