<?php

declare(strict_types=1);

namespace Spec\Runner;

use InvalidArgumentException;
use Spec\Claim\ClaimDefinition;
use Spec\Claim\EvidenceDefinition;
use Spec\Policy\OutcomePolicy;
use Spec\Probe\EngineProbe;
use Spec\Specification\DialectSpecification;
use Spec\Subject\FamilyRequest;
use Spec\Subject\SqlWitness;
use SqlFaker\Contract\Runtime;

/**
 * Runs claim catalogs against the Runtime contract, using spec-local dialect
 * metadata only for entry rules, family anchors, and witness generation.
 */
final class SpecRunner
{
    /** @var array<string, Runtime> */
    private array $runtimes;

    /** @var array<string, DialectSpecification> */
    private array $specifications;

    /** @var array<string, EngineProbe> */
    private array $probes;

    /** @var array<string, OutcomePolicy> */
    private array $policies;

    /** @var array<string, GrammarContractChecker> */
    private array $grammarCheckers = [];

    /**
     * @param array<string, Runtime> $runtimes
     * @param array<string, DialectSpecification> $specifications
     * @param array<string, EngineProbe> $probes
     * @param array<string, OutcomePolicy> $policies
     */
    public function __construct(array $runtimes, array $specifications, array $probes = [], array $policies = [])
    {
        $this->runtimes = $runtimes;
        $this->specifications = $specifications;
        $this->probes = $probes;
        $this->policies = $policies;
    }

    /**
     * @param list<ClaimDefinition> $claims
     * @return list<array<string, mixed>>
     */
    public function run(array $claims): array
    {
        $results = [];
        foreach ($claims as $claim) {
            $results[] = $this->runClaim($claim);
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function runClaim(ClaimDefinition $claim): array
    {
        $runtime = $this->runtimes[$claim->dialect] ?? null;
        if ($runtime === null) {
            throw new InvalidArgumentException(sprintf('No runtime registered for dialect %s.', $claim->dialect));
        }

        $specification = $this->specifications[$claim->dialect] ?? null;
        if ($specification === null) {
            throw new InvalidArgumentException(sprintf('No specification registered for dialect %s.', $claim->dialect));
        }

        $cases = [];
        foreach ($claim->cases as $index => $parameters) {
            $cases[] = $this->runClaimCase($claim, $runtime, $specification, $parameters, $index + 1);
        }

        $caseSummary = $this->summarizeEntries($cases);
        $checkSummary = $this->summarizeNestedChecks($cases);

        return [
            'claim_id' => $claim->id,
            'status' => $this->statusFromSummary($caseSummary),
            'dialect' => $claim->dialect,
            'level' => $claim->level,
            'statement' => $claim->statement,
            'source' => [
                'location' => $claim->sourceLocation,
            ],
            'subject' => [
                'kind' => $claim->subjectKind,
                'family_id' => $claim->familyId,
            ],
            'summary' => [
                'cases' => $caseSummary,
                'checks' => $checkSummary,
            ],
            'cases' => $cases,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @return array<string, mixed>
     */
    private function runClaimCase(
        ClaimDefinition $claim,
        Runtime $runtime,
        DialectSpecification $specification,
        array $parameters,
        int $caseNumber,
    ): array {
        $checks = [];
        $witness = null;
        foreach ($claim->evidence as $index => $evidence) {
            [$passed, $message, $facts, $witness] = $this->evaluateEvidence($claim, $runtime, $specification, $parameters, $evidence, $witness);
            $checks[] = [
                'check_number' => $index + 1,
                'kind' => $evidence->kind,
                'status' => $passed ? 'passed' : 'failed',
                'passed' => $passed,
                'message' => $message,
                'details' => $message,
                'options' => $evidence->options,
                'facts' => $facts,
            ];
        }

        $checkSummary = $this->summarizeEntries($checks);
        $caseResult = [
            'case_number' => $caseNumber,
            'parameters' => $parameters,
            'status' => $this->statusFromSummary($checkSummary),
            'summary' => [
                'checks' => $checkSummary,
            ],
            'checks' => $checks,
        ];

        if ($witness !== null) {
            $caseResult['witness'] = [
                'family_id' => $witness->familyId,
                'seed' => $witness->seed,
                'sql' => $witness->sql,
                'parameters' => $witness->parameters,
                'properties' => $witness->properties,
            ];
        }

        return $caseResult;
    }

    /**
     * @param array<string, scalar> $parameters
     * @return array{bool, string, array<string, mixed>, ?SqlWitness}
     */
    private function evaluateEvidence(
        ClaimDefinition $claim,
        Runtime $runtime,
        DialectSpecification $specification,
        array $parameters,
        EvidenceDefinition $evidence,
        ?SqlWitness $witness,
    ): array {
        return match ($evidence->kind) {
            'grammar.no_undefined_references' => $this->checkUndefinedReferences($claim->dialect, $runtime, $specification, $witness),
            'grammar.no_empty_rules' => $this->checkNoEmptyRules($claim->dialect, $runtime, $specification, $witness),
            'grammar.entries.present' => $this->checkEntriesPresent($claim->dialect, $runtime, $specification, $witness),
            'grammar.entries.terminate' => $this->checkEntriesTerminate($claim->dialect, $runtime, $specification, $witness),
            'grammar.families.reachable' => $this->checkFamiliesReachable($claim, $runtime, $specification, $evidence, $witness),
            'grammar.rule.contains_sequence' => $this->checkRuleSequence($claim->dialect, $runtime, $specification, $evidence, true, $witness),
            'grammar.rule.not_contains_sequence' => $this->checkRuleSequence($claim->dialect, $runtime, $specification, $evidence, false, $witness),
            'witness.generates' => $this->checkWitnessGenerates($claim, $runtime, $specification, $parameters, $witness),
            'witness.property_equals_parameter' => $this->checkWitnessPropertyEqualsParameter($claim, $runtime, $specification, $parameters, $evidence, $witness),
            'witness.properties_distinct' => $this->checkWitnessPropertiesDistinct($claim, $runtime, $specification, $parameters, $evidence, $witness),
            'outcome.kind_in' => $this->checkOutcomeKind($claim, $runtime, $specification, $parameters, $evidence, $witness),
            default => throw new InvalidArgumentException(sprintf('Unsupported evidence kind: %s', $evidence->kind)),
        };
    }

    /**
     * @return array{bool, string, array<string, mixed>, ?SqlWitness}
     */
    private function checkUndefinedReferences(string $dialect, Runtime $runtime, DialectSpecification $specification, ?SqlWitness $witness): array
    {
        $undefinedReferences = $this->grammarChecker($dialect, $runtime, $specification)->undefinedReferences();
        $passed = $undefinedReferences === [];

        return [
            $passed,
            $passed ? 'no undefined grammar references' : 'undefined grammar references found',
            [
                'undefined_references' => $undefinedReferences,
                'count' => count($undefinedReferences),
            ],
            $witness,
        ];
    }

    /**
     * @return array{bool, string, array<string, mixed>, ?SqlWitness}
     */
    private function checkNoEmptyRules(string $dialect, Runtime $runtime, DialectSpecification $specification, ?SqlWitness $witness): array
    {
        $rules = $this->grammarChecker($dialect, $runtime, $specification)->rulesWithoutAlternatives();
        $passed = $rules === [];

        return [
            $passed,
            $passed ? 'no empty grammar rules' : 'empty grammar rules found',
            [
                'rules' => $rules,
                'count' => count($rules),
            ],
            $witness,
        ];
    }

    /**
     * @return array{bool, string, array<string, mixed>, ?SqlWitness}
     */
    private function checkEntriesPresent(string $dialect, Runtime $runtime, DialectSpecification $specification, ?SqlWitness $witness): array
    {
        $entryRules = $specification->entryRules();
        $missing = $this->grammarChecker($dialect, $runtime, $specification)->missingEntries($entryRules);
        $passed = $missing === [];

        return [
            $passed,
            $passed ? 'all entry rules are present' : 'missing entry rules found',
            [
                'entry_rules' => $entryRules,
                'missing_entries' => $missing,
                'count' => count($missing),
            ],
            $witness,
        ];
    }

    /**
     * @return array{bool, string, array<string, mixed>, ?SqlWitness}
     */
    private function checkEntriesTerminate(string $dialect, Runtime $runtime, DialectSpecification $specification, ?SqlWitness $witness): array
    {
        $entryRules = $specification->entryRules();
        $nonTerminating = $this->grammarChecker($dialect, $runtime, $specification)->nonTerminatingReachableRules($entryRules);
        $passed = $nonTerminating === [];

        return [
            $passed,
            $passed ? 'all reachable entry rules terminate' : 'non-terminating reachable rules found',
            [
                'entry_rules' => $entryRules,
                'non_terminating_rules' => $nonTerminating,
                'count' => count($nonTerminating),
            ],
            $witness,
        ];
    }

    /**
     * @return array{bool, string, array<string, mixed>, ?SqlWitness}
     */
    private function checkFamiliesReachable(
        ClaimDefinition $claim,
        Runtime $runtime,
        DialectSpecification $specification,
        EvidenceDefinition $evidence,
        ?SqlWitness $witness,
    ): array {
        $families = $evidence->options['families'] ?? ($claim->familyId !== null ? [$claim->familyId] : null);
        if (!is_array($families) || $families === []) {
            throw new InvalidArgumentException('grammar.families.reachable requires families or a family subject.');
        }

        $familyIds = [];
        foreach ($families as $familyId) {
            if (!is_string($familyId) || $familyId === '') {
                throw new InvalidArgumentException('grammar.families.reachable requires string family ids.');
            }
            $familyIds[] = $familyId;
        }

        $unreachable = $this->grammarChecker($claim->dialect, $runtime, $specification)->unreachableFamilies($specification->entryRules(), $familyIds);
        $passed = $unreachable === [];

        return [
            $passed,
            $passed ? 'all required families are reachable' : 'unreachable families found',
            [
                'families' => $familyIds,
                'unreachable_families' => $unreachable,
                'count' => count($unreachable),
            ],
            $witness,
        ];
    }

    /**
     * @return array{bool, string, array<string, mixed>, ?SqlWitness}
     */
    private function checkRuleSequence(
        string $dialect,
        Runtime $runtime,
        DialectSpecification $specification,
        EvidenceDefinition $evidence,
        bool $shouldExist,
        ?SqlWitness $witness,
    ): array {
        $rule = $evidence->options['rule'] ?? null;
        $sequence = $evidence->options['sequence'] ?? null;
        if (!is_string($rule) || $rule === '') {
            throw new InvalidArgumentException('grammar.rule.* evidence requires a rule option.');
        }

        if (!is_array($sequence) || $sequence === []) {
            throw new InvalidArgumentException('grammar.rule.* evidence requires a non-empty sequence option.');
        }

        $expected = [];
        foreach ($sequence as $symbol) {
            if (!is_string($symbol) || (!str_starts_with($symbol, 't:') && !str_starts_with($symbol, 'nt:'))) {
                throw new InvalidArgumentException('grammar.rule.* sequence entries must use t: or nt: prefixes.');
            }
            $expected[] = $symbol;
        }

        $ruleSnapshot = $runtime->supportedGrammar()->rule($rule);
        $baseFacts = [
            'rule' => $rule,
            'expectation' => $shouldExist ? 'sequence must exist' : 'sequence must be excluded',
            'expected_sequence' => $expected,
        ];
        if ($ruleSnapshot === null) {
            return [
                false,
                sprintf('rule %s is missing', $rule),
                $baseFacts + [
                    'rule_found' => false,
                    'sequence_present' => false,
                    'matched' => false,
                ],
                $witness,
            ];
        }

        $sequencePresent = false;
        foreach ($ruleSnapshot->alternatives as $alternative) {
            if ($alternative->sequence() === $expected) {
                $sequencePresent = true;
                break;
            }
        }

        $passed = $shouldExist ? $sequencePresent : !$sequencePresent;

        return [
            $passed,
            match (true) {
                $shouldExist && $passed => sprintf('%s contains the expected sequence', $rule),
                $shouldExist => sprintf('%s does not contain the expected sequence', $rule),
                $passed => sprintf('%s excludes the expected sequence', $rule),
                default => sprintf('%s still contains the excluded sequence', $rule),
            },
            $baseFacts + [
                'rule_found' => true,
                'sequence_present' => $sequencePresent,
                'matched' => $passed,
            ],
            $witness,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @return array{bool, string, array<string, mixed>, ?SqlWitness}
     */
    private function checkWitnessGenerates(
        ClaimDefinition $claim,
        Runtime $runtime,
        DialectSpecification $specification,
        array $parameters,
        ?SqlWitness $witness,
    ): array {
        $witness = $this->requireWitness($claim, $runtime, $specification, $parameters, $witness);

        return [
            true,
            'witness generated',
            [
                'family_id' => $witness->familyId,
                'seed' => $witness->seed,
                'properties' => $witness->properties,
            ],
            $witness,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @return array{bool, string, array<string, mixed>, ?SqlWitness}
     */
    private function checkWitnessPropertyEqualsParameter(
        ClaimDefinition $claim,
        Runtime $runtime,
        DialectSpecification $specification,
        array $parameters,
        EvidenceDefinition $evidence,
        ?SqlWitness $witness,
    ): array {
        $property = $evidence->options['property'] ?? null;
        $parameter = $evidence->options['parameter'] ?? null;
        if (!is_string($property) || !is_string($parameter)) {
            throw new InvalidArgumentException('witness.property_equals_parameter requires property and parameter options.');
        }

        $witness = $this->requireWitness($claim, $runtime, $specification, $parameters, $witness);
        $actual = $witness->properties[$property] ?? null;
        $expected = $parameters[$parameter] ?? null;
        $passed = $actual === $expected;

        return [
            $passed,
            $passed
                ? sprintf('witness property %s matches parameter %s', $property, $parameter)
                : sprintf('witness property %s does not match parameter %s', $property, $parameter),
            [
                'property' => $property,
                'parameter' => $parameter,
                'actual' => $actual,
                'expected' => $expected,
                'matched' => $passed,
            ],
            $witness,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @return array{bool, string, array<string, mixed>, ?SqlWitness}
     */
    private function checkWitnessPropertiesDistinct(
        ClaimDefinition $claim,
        Runtime $runtime,
        DialectSpecification $specification,
        array $parameters,
        EvidenceDefinition $evidence,
        ?SqlWitness $witness,
    ): array {
        $propertyNames = $evidence->options['properties'] ?? null;
        if (!is_array($propertyNames) || count($propertyNames) < 2) {
            throw new InvalidArgumentException('witness.properties_distinct requires a properties list with at least two entries.');
        }

        $witness = $this->requireWitness($claim, $runtime, $specification, $parameters, $witness);

        $values = [];
        foreach ($propertyNames as $propertyName) {
            if (!is_string($propertyName) || $propertyName === '') {
                throw new InvalidArgumentException('witness.properties_distinct property names must be non-empty strings.');
            }

            $value = $witness->properties[$propertyName] ?? null;
            if (!is_scalar($value)) {
                return [
                    false,
                    sprintf('missing scalar witness property %s', $propertyName),
                    [
                        'properties' => $values,
                        'missing_property' => $propertyName,
                        'distinct' => false,
                    ],
                    $witness,
                ];
            }

            $values[$propertyName] = $value;
        }

        $distinct = array_unique(array_map(
            static fn (string|int|float|bool $value): string => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
            array_values($values),
        ));
        $passed = count($distinct) === count($values);

        return [
            $passed,
            $passed ? 'witness properties are distinct' : 'witness properties are not distinct',
            [
                'properties' => $values,
                'distinct' => $passed,
            ],
            $witness,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @return array{bool, string, array<string, mixed>, ?SqlWitness}
     */
    private function checkOutcomeKind(
        ClaimDefinition $claim,
        Runtime $runtime,
        DialectSpecification $specification,
        array $parameters,
        EvidenceDefinition $evidence,
        ?SqlWitness $witness,
    ): array {
        $allowedKinds = $evidence->options['allowedKinds'] ?? null;
        if (!is_array($allowedKinds) || $allowedKinds === []) {
            throw new InvalidArgumentException('outcome.kind_in requires an allowedKinds list.');
        }

        $allowed = [];
        $normalizedAllowedKinds = [];
        foreach ($allowedKinds as $kind) {
            if (!is_string($kind) || $kind === '') {
                throw new InvalidArgumentException('outcome.kind_in allowedKinds must contain non-empty strings.');
            }
            $allowed[$kind] = true;
            $normalizedAllowedKinds[] = $kind;
        }

        $probe = $this->probes[$claim->dialect] ?? null;
        if ($probe === null) {
            throw new InvalidArgumentException(sprintf('No engine probe registered for dialect %s.', $claim->dialect));
        }

        $policy = $this->policies[$claim->dialect] ?? null;
        if ($policy === null) {
            throw new InvalidArgumentException(sprintf('No outcome policy registered for dialect %s.', $claim->dialect));
        }

        $witness = $this->requireWitness($claim, $runtime, $specification, $parameters, $witness);
        $probeResult = $probe->observe($witness->sql);
        $kind = $policy->classify($probeResult)->value;
        $passed = isset($allowed[$kind]);

        return [
            $passed,
            $passed
                ? sprintf('observed outcome %s is allowed', $kind)
                : sprintf('observed outcome %s is not allowed', $kind),
            [
                'allowed_kinds' => $normalizedAllowedKinds,
                'actual_kind' => $kind,
                'accepted' => $probeResult->accepted,
                'phase' => $probeResult->phase->value,
                'sqlstate' => $probeResult->sqlState,
                'error_code' => $probeResult->errorCode,
                'error_message' => $probeResult->message,
            ],
            $witness,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     */
    private function requireWitness(
        ClaimDefinition $claim,
        Runtime $runtime,
        DialectSpecification $specification,
        array $parameters,
        ?SqlWitness $witness,
    ): SqlWitness {
        if ($witness !== null) {
            return $witness;
        }

        if ($claim->subjectKind !== 'family' || $claim->familyId === null) {
            throw new InvalidArgumentException(sprintf('Claim %s requires a family subject to generate witnesses.', $claim->id));
        }

        return $specification->generateWitness($runtime, new FamilyRequest($claim->familyId, $parameters));
    }

    private function grammarChecker(string $dialect, Runtime $runtime, DialectSpecification $specification): GrammarContractChecker
    {
        return $this->grammarCheckers[$dialect] ??= new GrammarContractChecker(
            $runtime->supportedGrammar(),
            $this->familyAnchors($specification),
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function familyAnchors(DialectSpecification $specification): array
    {
        $anchors = [];
        foreach ($specification->familyCatalog() as $family) {
            $anchors[$family->id] = $family->anchorRules;
        }

        return $anchors;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return array{total: int, passed: int, failed: int}
     */
    private function summarizeEntries(array $entries): array
    {
        $summary = [
            'total' => count($entries),
            'passed' => 0,
            'failed' => 0,
        ];

        foreach ($entries as $entry) {
            if (($entry['status'] ?? null) === 'passed') {
                $summary['passed']++;
                continue;
            }

            $summary['failed']++;
        }

        return $summary;
    }

    /**
     * @param list<array<string, mixed>> $cases
     * @return array{total: int, passed: int, failed: int}
     */
    private function summarizeNestedChecks(array $cases): array
    {
        $summary = [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
        ];

        foreach ($cases as $case) {
            $checks = $case['checks'] ?? null;
            if (!is_array($checks)) {
                continue;
            }

            foreach ($checks as $check) {
                if (!is_array($check)) {
                    continue;
                }

                $summary['total']++;
                if (($check['status'] ?? null) === 'passed') {
                    $summary['passed']++;
                    continue;
                }

                $summary['failed']++;
            }
        }

        return $summary;
    }

    /**
     * @param array{total: int, passed: int, failed: int} $summary
     */
    private function statusFromSummary(array $summary): string
    {
        return $summary['failed'] === 0 ? 'passed' : 'failed';
    }
}
