<?php

declare(strict_types=1);

namespace Spec\Runner;

use InvalidArgumentException;
use Spec\Claim\ClaimDefinition;
use Spec\Claim\EvidenceDefinition;
use Spec\Policy\OutcomePolicy;
use Spec\Probe\EngineProbe;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;

/**
 * Runs claim catalogs directly against the algorithm-phase contracts.
 */
final class SpecRunner
{
    /** @var array<string, DialectContracts> */
    private array $dialectContracts;

    /** @var array<string, EngineProbe> */
    private array $probes;

    /** @var array<string, OutcomePolicy> */
    private array $policies;

    /** @var array<string, GrammarContractChecker> */
    private array $grammarCheckers = [];

    /**
     * @param array<string, DialectContracts> $dialectContracts
     * @param array<string, EngineProbe> $probes
     * @param array<string, OutcomePolicy> $policies
     */
    public function __construct(array $dialectContracts, array $probes = [], array $policies = [])
    {
        $this->dialectContracts = $dialectContracts;
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
        $contracts = $this->dialectContracts[$claim->dialect] ?? null;
        if ($contracts === null) {
            throw new InvalidArgumentException(sprintf('No contracts registered for dialect %s.', $claim->dialect));
        }

        $cases = [];
        foreach ($claim->cases as $index => $parameters) {
            $cases[] = $this->runClaimCase($claim, $contracts, $parameters, $index + 1);
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
            'subject' => ['kind' => $claim->subjectKind] + $claim->subjectOptions,
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
        DialectContracts $contracts,
        array $parameters,
        int $caseNumber,
    ): array {
        $checks = [];
        $generation = null;
        foreach ($claim->evidence as $index => $evidence) {
            [$passed, $message, $facts, $generation] = $this->evaluateEvidence($claim, $contracts, $parameters, $evidence, $generation);
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

        if ($generation !== null) {
            $caseResult['generation'] = $generation;
        }

        return $caseResult;
    }

    /**
     * @param array<string, scalar> $parameters
     * @param null|array{request: array<string, scalar|null>, sql: string} $generation
     * @return array{bool, string, array<string, mixed>, null|array{request: array<string, scalar|null>, sql: string}}
     */
    private function evaluateEvidence(
        ClaimDefinition $claim,
        DialectContracts $contracts,
        array $parameters,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        return match ($evidence->kind) {
            'grammar.no_undefined_references' => $this->checkUndefinedReferences($claim->dialect, $contracts, $generation),
            'grammar.no_empty_rules' => $this->checkNoEmptyRules($claim->dialect, $contracts, $generation),
            'grammar.entries.present' => $this->checkEntriesPresent($claim->dialect, $contracts, $evidence, $generation),
            'grammar.entries.terminate' => $this->checkEntriesTerminate($claim->dialect, $contracts, $evidence, $generation),
            'grammar.rules.reachable' => $this->checkRulesReachable($claim->dialect, $contracts, $evidence, $generation),
            'grammar.rule.contains_sequence' => $this->checkRuleSequence($claim->dialect, $contracts, $evidence, true, $generation),
            'grammar.rule.not_contains_sequence' => $this->checkRuleSequence($claim->dialect, $contracts, $evidence, false, $generation),
            'generation.generates' => $this->checkGenerationGenerates($claim, $contracts, $parameters, $generation),
            'generation.sql_matches' => $this->checkGenerationSqlMatches($claim, $contracts, $parameters, $evidence, $generation),
            'outcome.kind_in' => $this->checkOutcomeKind($claim, $contracts, $parameters, $evidence, $generation),
            default => throw new InvalidArgumentException(sprintf('Unsupported evidence kind: %s', $evidence->kind)),
        };
    }

    /**
     * @param null|array{request: array<string, scalar|null>, sql: string} $generation
     * @return array{bool, string, array<string, mixed>, null|array{request: array<string, scalar|null>, sql: string}}
     */
    private function checkUndefinedReferences(string $dialect, DialectContracts $contracts, ?array $generation): array
    {
        $undefinedReferences = $this->grammarChecker($dialect, $contracts)->undefinedReferences();
        $passed = $undefinedReferences === [];

        return [
            $passed,
            $passed ? 'no undefined grammar references' : 'undefined grammar references found',
            [
                'undefined_references' => $undefinedReferences,
                'count' => count($undefinedReferences),
            ],
            $generation,
        ];
    }

    /**
     * @param null|array{request: array<string, scalar|null>, sql: string} $generation
     * @return array{bool, string, array<string, mixed>, null|array{request: array<string, scalar|null>, sql: string}}
     */
    private function checkNoEmptyRules(string $dialect, DialectContracts $contracts, ?array $generation): array
    {
        $rules = $this->grammarChecker($dialect, $contracts)->rulesWithoutAlternatives();
        $passed = $rules === [];

        return [
            $passed,
            $passed ? 'no empty grammar rules' : 'empty grammar rules found',
            [
                'rules' => $rules,
                'count' => count($rules),
            ],
            $generation,
        ];
    }

    /**
     * @param null|array{request: array<string, scalar|null>, sql: string} $generation
     * @return array{bool, string, array<string, mixed>, null|array{request: array<string, scalar|null>, sql: string}}
     */
    private function checkEntriesPresent(
        string $dialect,
        DialectContracts $contracts,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        $entryRules = $this->requireStringListOption($evidence, 'entries');
        $missing = $this->grammarChecker($dialect, $contracts)->missingEntries($entryRules);
        $passed = $missing === [];

        return [
            $passed,
            $passed ? 'all entry rules are present' : 'missing entry rules found',
            [
                'entry_rules' => $entryRules,
                'missing_entries' => $missing,
                'count' => count($missing),
            ],
            $generation,
        ];
    }

    /**
     * @param null|array{request: array<string, scalar|null>, sql: string} $generation
     * @return array{bool, string, array<string, mixed>, null|array{request: array<string, scalar|null>, sql: string}}
     */
    private function checkEntriesTerminate(
        string $dialect,
        DialectContracts $contracts,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        $entryRules = $this->requireStringListOption($evidence, 'entries');
        $nonTerminating = $this->grammarChecker($dialect, $contracts)->nonTerminatingReachableRules($entryRules);
        $passed = $nonTerminating === [];

        return [
            $passed,
            $passed ? 'all reachable entry rules terminate' : 'non-terminating reachable rules found',
            [
                'entry_rules' => $entryRules,
                'non_terminating_rules' => $nonTerminating,
                'count' => count($nonTerminating),
            ],
            $generation,
        ];
    }

    /**
     * @param null|array{request: array<string, scalar|null>, sql: string} $generation
     * @return array{bool, string, array<string, mixed>, null|array{request: array<string, scalar|null>, sql: string}}
     */
    private function checkRulesReachable(
        string $dialect,
        DialectContracts $contracts,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        $entryRules = $this->requireStringListOption($evidence, 'entries');
        $rules = $this->requireStringListOption($evidence, 'rules');
        $unreachable = $this->grammarChecker($dialect, $contracts)->unreachableRules($entryRules, $rules);
        $passed = $unreachable === [];

        return [
            $passed,
            $passed ? 'all required rules are reachable' : 'unreachable rules found',
            [
                'entry_rules' => $entryRules,
                'rules' => $rules,
                'unreachable_rules' => $unreachable,
                'count' => count($unreachable),
            ],
            $generation,
        ];
    }

    /**
     * @param null|array{request: array<string, scalar|null>, sql: string} $generation
     * @return array{bool, string, array<string, mixed>, null|array{request: array<string, scalar|null>, sql: string}}
     */
    private function checkRuleSequence(
        string $dialect,
        DialectContracts $contracts,
        EvidenceDefinition $evidence,
        bool $shouldExist,
        ?array $generation,
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

        $ruleSnapshot = $this->supportedGrammar($dialect, $contracts)->rule($rule);
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
                $generation,
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
            $generation,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @param null|array{request: array<string, scalar|null>, sql: string} $generation
     * @return array{bool, string, array<string, mixed>, array{request: array<string, scalar|null>, sql: string}}
     */
    private function checkGenerationGenerates(
        ClaimDefinition $claim,
        DialectContracts $contracts,
        array $parameters,
        ?array $generation,
    ): array {
        $generation = $this->requireGeneration($claim, $contracts, $parameters, $generation);

        return [
            $generation['sql'] !== '',
            $generation['sql'] !== '' ? 'generation completed' : 'generation returned an empty string',
            [
                'request' => $generation['request'],
                'sql_length' => strlen($generation['sql']),
            ],
            $generation,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @param null|array{request: array<string, scalar|null>, sql: string} $generation
     * @return array{bool, string, array<string, mixed>, array{request: array<string, scalar|null>, sql: string}}
     */
    private function checkGenerationSqlMatches(
        ClaimDefinition $claim,
        DialectContracts $contracts,
        array $parameters,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        $generation = $this->requireGeneration($claim, $contracts, $parameters, $generation);
        $pattern = $this->requirePattern($parameters, $evidence);
        $matched = preg_match($pattern, $generation['sql']) === 1;

        return [
            $matched,
            $matched ? 'generated SQL matches the expected pattern' : 'generated SQL does not match the expected pattern',
            [
                'request' => $generation['request'],
                'pattern' => $pattern,
                'matched' => $matched,
            ],
            $generation,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @param null|array{request: array<string, scalar|null>, sql: string} $generation
     * @return array{bool, string, array<string, mixed>, array{request: array<string, scalar|null>, sql: string}}
     */
    private function checkOutcomeKind(
        ClaimDefinition $claim,
        DialectContracts $contracts,
        array $parameters,
        EvidenceDefinition $evidence,
        ?array $generation,
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

        $generation = $this->requireGeneration($claim, $contracts, $parameters, $generation);
        $probeResult = $probe->observe($generation['sql']);
        $kind = $policy->classify($probeResult)->value;
        $passed = isset($allowed[$kind]);

        return [
            $passed,
            $passed
                ? sprintf('observed outcome %s is allowed', $kind)
                : sprintf('observed outcome %s is not allowed', $kind),
            [
                'request' => $generation['request'],
                'allowed_kinds' => $normalizedAllowedKinds,
                'actual_kind' => $kind,
                'accepted' => $probeResult->accepted,
                'phase' => $probeResult->phase->value,
                'sqlstate' => $probeResult->sqlState,
                'error_code' => $probeResult->errorCode,
                'error_message' => $probeResult->message,
            ],
            $generation,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @param null|array{request: array<string, scalar|null>, sql: string} $generation
     * @return array{request: array<string, scalar|null>, sql: string}
     */
    private function requireGeneration(
        ClaimDefinition $claim,
        DialectContracts $contracts,
        array $parameters,
        ?array $generation,
    ): array {
        if ($generation !== null) {
            return $generation;
        }

        if ($claim->subjectKind !== 'generation') {
            throw new InvalidArgumentException(sprintf('Claim %s requires a generation subject.', $claim->id));
        }

        $request = $this->generationRequest($claim, $parameters);

        return [
            'request' => [
                'start_rule' => $request->startRule,
                'seed' => $request->seed,
                'max_depth' => $request->maxDepth,
            ],
            'sql' => $contracts->statementGenerator->generate($request),
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     */
    private function generationRequest(ClaimDefinition $claim, array $parameters): GenerationRequest
    {
        $startRule = $this->stringSetting($claim, $parameters, 'start_rule');
        $seed = $this->requiredIntSetting($claim, $parameters, 'seed');
        $maxDepth = $this->intSetting($claim, $parameters, 'max_depth') ?? PHP_INT_MAX;

        return new GenerationRequest(
            startRule: $startRule,
            seed: $seed,
            maxDepth: $maxDepth,
        );
    }

    /**
     * @param array<string, scalar> $parameters
     */
    private function requirePattern(array $parameters, EvidenceDefinition $evidence): string
    {
        $pattern = $evidence->options['pattern'] ?? null;
        if ($pattern === null) {
            $parameterName = $evidence->options['parameter'] ?? null;
            if (!is_string($parameterName) || $parameterName === '') {
                throw new InvalidArgumentException('generation.sql_matches requires a pattern or parameter option.');
            }

            $pattern = $parameters[$parameterName] ?? null;
        }

        if (!is_string($pattern) || $pattern === '') {
            throw new InvalidArgumentException('generation.sql_matches requires a non-empty pattern.');
        }

        if (@preg_match($pattern, '') === false) {
            throw new InvalidArgumentException(sprintf('generation.sql_matches received an invalid pattern: %s', $pattern));
        }

        return $pattern;
    }

    /**
     * @param array<string, scalar> $parameters
     */
    private function stringSetting(ClaimDefinition $claim, array $parameters, string $key): ?string
    {
        $value = $parameters[$key] ?? ($claim->subjectOptions[$key] ?? null);
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf('Claim %s requires %s to be a non-empty string when provided.', $claim->id, $key));
        }

        return $value;
    }

    /**
     * @param array<string, scalar> $parameters
     */
    private function intSetting(ClaimDefinition $claim, array $parameters, string $key): ?int
    {
        $value = $parameters[$key] ?? ($claim->subjectOptions[$key] ?? null);
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new InvalidArgumentException(sprintf('Claim %s requires %s to be an integer.', $claim->id, $key));
    }

    /**
     * @param array<string, scalar> $parameters
     */
    private function requiredIntSetting(ClaimDefinition $claim, array $parameters, string $key): int
    {
        $value = $this->intSetting($claim, $parameters, $key);
        if ($value === null) {
            throw new InvalidArgumentException(sprintf('Claim %s requires %s.', $claim->id, $key));
        }

        return $value;
    }

    private function grammarChecker(string $dialect, DialectContracts $contracts): GrammarContractChecker
    {
        return $this->grammarCheckers[$dialect] ??= new GrammarContractChecker(
            $this->supportedGrammar($dialect, $contracts),
        );
    }

    private function supportedGrammar(string $dialect, DialectContracts $contracts): Grammar
    {
        return $contracts->supportedGrammar();
    }

    /**
     * @return list<string>
     */
    private function requireStringListOption(EvidenceDefinition $evidence, string $key): array
    {
        $values = $evidence->options[$key] ?? null;
        if (!is_array($values) || $values === []) {
            throw new InvalidArgumentException(sprintf('Evidence kind %s requires a non-empty %s list.', $evidence->kind, $key));
        }

        $result = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new InvalidArgumentException(sprintf('Evidence kind %s requires %s entries to be non-empty strings.', $evidence->kind, $key));
            }

            $result[] = $value;
        }

        return $result;
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
