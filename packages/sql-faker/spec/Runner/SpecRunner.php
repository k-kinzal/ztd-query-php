<?php

declare(strict_types=1);

namespace Spec\Runner;

use InvalidArgumentException;
use Spec\Claim\ClaimDefinition;
use Spec\Claim\EvidenceDefinition;
use Spec\Policy\OutcomePolicy;
use Spec\Probe\EngineProbe;
use Spec\Support\GrammarFingerprint;
use SqlFaker\Contract\GenerationRequest;
use SqlFaker\Contract\Grammar;
use Throwable;

/**
 * Runs claim catalogs directly against the algorithm-phase contracts.
 */
final class SpecRunner
{
    /** @var array<string, DialectRuntime> */
    private array $dialectRuntimes;

    /** @var array<string, EngineProbe> */
    private array $probes;

    /** @var array<string, OutcomePolicy> */
    private array $policies;

    /** @var array<string, GrammarContractChecker> */
    private array $grammarCheckers = [];

    /**
     * @var null|array{
     *     sql: string,
     *     actual_kind: string,
     *     accepted: bool,
     *     phase: string,
     *     sqlstate: null|string,
     *     error_code: null|int,
     *     error_message: null|string
     * }
     */
    private ?array $outcomeObservation = null;

    /**
     * @param array<string, DialectRuntime> $dialectRuntimes
     * @param array<string, EngineProbe> $probes
     * @param array<string, OutcomePolicy> $policies
     */
    public function __construct(array $dialectRuntimes, array $probes = [], array $policies = [])
    {
        $this->dialectRuntimes = $dialectRuntimes;
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
        $runtime = $this->dialectRuntimes[$claim->dialect] ?? null;
        if ($runtime === null) {
            throw new InvalidArgumentException(sprintf('No runtimes registered for dialect %s.', $claim->dialect));
        }

        $cases = [];
        foreach ($claim->cases as $index => $parameters) {
            $cases[] = $this->runClaimCase($claim, $runtime, $parameters, $index + 1);
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
        DialectRuntime $runtime,
        array $parameters,
        int $caseNumber,
    ): array {
        $this->outcomeObservation = null;
        $checks = [];
        $generation = null;
        foreach ($claim->evidence as $index => $evidence) {
            [$passed, $message, $facts, $generation] = $this->evaluateEvidence($claim, $runtime, $parameters, $evidence, $generation);
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

        $this->outcomeObservation = null;

        return $caseResult;
    }

    /**
     * @param array<string, scalar> $parameters
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     null|array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function evaluateEvidence(
        ClaimDefinition $claim,
        DialectRuntime $runtime,
        array $parameters,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        return match ($evidence->kind) {
            'grammar.no_undefined_references' => $this->checkUndefinedReferences($claim, $runtime, $generation),
            'grammar.no_empty_rules' => $this->checkNoEmptyRules($claim, $runtime, $generation),
            'grammar.entries.present' => $this->checkEntriesPresent($claim, $runtime, $evidence, $generation),
            'grammar.entries.terminate' => $this->checkEntriesTerminate($claim, $runtime, $evidence, $generation),
            'grammar.rules.reachable' => $this->checkRulesReachable($claim, $runtime, $evidence, $generation),
            'grammar.fingerprint_matches' => $this->checkGrammarFingerprint($claim, $runtime, $evidence, $generation),
            'grammar.rewrite_steps_match' => $this->checkRewriteStepsMatch($claim, $runtime, $evidence, $generation),
            'grammar.termination_lengths_match' => $this->checkTerminationLengthsMatch($claim, $runtime, $evidence, $generation),
            'grammar.rule.contains_sequence' => $this->checkRuleSequence($claim, $runtime, $evidence, true, $generation),
            'grammar.rule.not_contains_sequence' => $this->checkRuleSequence($claim, $runtime, $evidence, false, $generation),
            'generation.generates' => $this->checkGenerationGenerates($claim, $runtime, $parameters, $generation),
            'generation.deterministic' => $this->checkGenerationDeterministic($claim, $runtime, $parameters, $generation),
            'generation.fails' => $this->checkGenerationFails($claim, $runtime, $parameters, $evidence, $generation),
            'generation.terminals_equal' => $this->checkGenerationTerminalsEqual($claim, $runtime, $parameters, $evidence, $generation),
            'generation.sql_matches' => $this->checkGenerationSqlMatches($claim, $runtime, $parameters, $evidence, $generation),
            'outcome.kind_in' => $this->checkOutcomeKind($claim, $runtime, $parameters, $evidence, $generation),
            'outcome.phase_is' => $this->checkOutcomePhase($claim, $runtime, $parameters, $evidence, $generation),
            default => throw new InvalidArgumentException(sprintf('Unsupported evidence kind: %s', $evidence->kind)),
        };
    }

    /**
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     null|array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkUndefinedReferences(ClaimDefinition $claim, DialectRuntime $runtime, ?array $generation): array
    {
        $undefinedReferences = $this->grammarChecker($claim, $runtime)->undefinedReferences();
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
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     null|array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkNoEmptyRules(ClaimDefinition $claim, DialectRuntime $runtime, ?array $generation): array
    {
        $rules = $this->grammarChecker($claim, $runtime)->rulesWithoutAlternatives();
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
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     null|array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkEntriesPresent(
        ClaimDefinition $claim,
        DialectRuntime $contracts,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        $entryRules = $this->requireStringListOption($evidence, 'entries');
        $missing = $this->grammarChecker($claim, $contracts)->missingEntries($entryRules);
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
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     null|array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkEntriesTerminate(
        ClaimDefinition $claim,
        DialectRuntime $contracts,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        $entryRules = $this->requireStringListOption($evidence, 'entries');
        $nonTerminating = $this->grammarChecker($claim, $contracts)->nonTerminatingReachableRules($entryRules);
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
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     null|array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkRulesReachable(
        ClaimDefinition $claim,
        DialectRuntime $contracts,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        $entryRules = $this->requireStringListOption($evidence, 'entries');
        $rules = $this->requireStringListOption($evidence, 'rules');
        $unreachable = $this->grammarChecker($claim, $contracts)->unreachableRules($entryRules, $rules);
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
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     null|array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkRuleSequence(
        ClaimDefinition $claim,
        DialectRuntime $contracts,
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

        $ruleSnapshot = $this->grammar($claim, $contracts)->rule($rule);
        $baseFacts = [
            'rule' => $rule,
            'subject_kind' => $claim->subjectKind,
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
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     null|array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkRewriteStepsMatch(
        ClaimDefinition $claim,
        DialectRuntime $runtime,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        if ($claim->subjectKind !== 'grammar') {
            throw new InvalidArgumentException(sprintf('Claim %s requires a grammar subject for grammar.rewrite_steps_match.', $claim->id));
        }

        $expected = $this->requireStringListOption($evidence, 'step_ids');
        $actual = $runtime->rewriteProgram()->stepIds();
        $matched = $actual === $expected;

        return [
            $matched,
            $matched ? 'rewrite step order matches' : 'rewrite step order does not match',
            [
                'expected_step_ids' => $expected,
                'actual_step_ids' => $actual,
                'matched' => $matched,
            ],
            $generation,
        ];
    }

    /**
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     null|array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkTerminationLengthsMatch(
        ClaimDefinition $claim,
        DialectRuntime $runtime,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        if ($claim->subjectKind !== 'grammar') {
            throw new InvalidArgumentException(sprintf('Claim %s requires a grammar subject for grammar.termination_lengths_match.', $claim->id));
        }

        $expected = $this->requireLengthMapOption($evidence, 'lengths');
        $actual = [];
        $matched = true;
        $lengths = $runtime->terminationLengths();
        foreach ($expected as $rule => $expectedLength) {
            $actual[$rule] = $lengths->lengthOf($rule);
            if ($actual[$rule] !== $expectedLength) {
                $matched = false;
            }
        }

        return [
            $matched,
            $matched ? 'termination lengths match' : 'termination lengths do not match',
            [
                'expected_lengths' => $expected,
                'actual_lengths' => $actual,
                'matched' => $matched,
            ],
            $generation,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkGenerationGenerates(
        ClaimDefinition $claim,
        DialectRuntime $contracts,
        array $parameters,
        ?array $generation,
    ): array {
        $generation = $this->generationAttempt($claim, $contracts, $parameters, $generation);

        return [
            $generation['succeeded'] && $generation['sql'] !== '',
            $generation['succeeded'] && $generation['sql'] !== ''
                ? 'generation completed'
                : ($generation['succeeded'] ? 'generation returned an empty string' : 'generation failed'),
            [
                'request' => $generation['request'],
                'succeeded' => $generation['succeeded'],
                'sql_length' => strlen((string) $generation['sql']),
                'exception_class' => $generation['exception_class'],
                'exception_message' => $generation['exception_message'],
            ],
            $generation,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     null|array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkGenerationTerminalsEqual(
        ClaimDefinition $claim,
        DialectRuntime $runtime,
        array $parameters,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        if ($claim->subjectKind !== 'generation') {
            throw new InvalidArgumentException(sprintf('Claim %s requires a generation subject.', $claim->id));
        }

        $expected = $this->requireStringListOption($evidence, 'terminals');
        $request = $this->generationRequest($claim, $parameters);
        $requestFacts = [
            'start_rule' => $request->startRule,
            'seed' => $request->seed,
            'max_depth' => $request->maxDepth,
        ];

        try {
            $terminals = $runtime->derive($request)->terminals;
        } catch (Throwable $e) {
            return [
                false,
                'terminal derivation failed before comparison',
                [
                    'request' => $requestFacts,
                    'expected_terminals' => $expected,
                    'exception_class' => $e::class,
                    'exception_message' => $e->getMessage(),
                ],
                $generation,
            ];
        }

        $matched = $terminals === $expected;

        return [
            $matched,
            $matched ? 'derived terminals match the expected sequence' : 'derived terminals do not match the expected sequence',
            [
                'request' => $requestFacts,
                'expected_terminals' => $expected,
                'actual_terminals' => $terminals,
                'matched' => $matched,
            ],
            $generation,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkGenerationDeterministic(
        ClaimDefinition $claim,
        DialectRuntime $contracts,
        array $parameters,
        ?array $generation,
    ): array {
        $generation = $this->generationAttempt($claim, $contracts, $parameters, $generation);
        if (!$generation['succeeded'] || !is_string($generation['sql'])) {
            return [
                false,
                'generation failed before determinism could be checked',
                [
                    'request' => $generation['request'],
                    'succeeded' => false,
                    'exception_class' => $generation['exception_class'],
                    'exception_message' => $generation['exception_message'],
                ],
                $generation,
            ];
        }

        $request = $this->generationRequest($claim, $parameters);
        try {
            $secondSql = $contracts->generate($request);
            $matched = $generation['sql'] === $secondSql;
        } catch (Throwable $e) {
            return [
                false,
                'second generation failed during determinism check',
                [
                    'request' => $generation['request'],
                    'first_sql' => $generation['sql'],
                    'second_generation_exception_class' => $e::class,
                    'second_generation_exception_message' => $e->getMessage(),
                ],
                $generation,
            ];
        }

        return [
            $matched,
            $matched ? 'repeated generation is deterministic' : 'repeated generation is not deterministic',
            [
                'request' => $generation['request'],
                'first_sql' => $generation['sql'],
                'second_sql' => $secondSql,
                'matched' => $matched,
            ],
            $generation,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkGenerationFails(
        ClaimDefinition $claim,
        DialectRuntime $contracts,
        array $parameters,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        $generation = $this->generationAttempt($claim, $contracts, $parameters, $generation);
        $pattern = $this->requireFailurePattern($parameters, $evidence);
        $exceptionMessage = $generation['exception_message'] ?? '';
        $matched = !$generation['succeeded'] && preg_match($pattern, $exceptionMessage) === 1;

        return [
            $matched,
            $matched ? 'generation failed as expected' : 'generation did not fail with the expected message',
            [
                'request' => $generation['request'],
                'succeeded' => $generation['succeeded'],
                'pattern' => $pattern,
                'exception_class' => $generation['exception_class'],
                'exception_message' => $generation['exception_message'],
                'matched' => $matched,
            ],
            $generation,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkGenerationSqlMatches(
        ClaimDefinition $claim,
        DialectRuntime $contracts,
        array $parameters,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        $generation = $this->generationAttempt($claim, $contracts, $parameters, $generation);
        if (!$generation['succeeded'] || !is_string($generation['sql'])) {
            return [
                false,
                'generation failed before SQL pattern matching',
                [
                    'request' => $generation['request'],
                    'succeeded' => false,
                    'exception_class' => $generation['exception_class'],
                    'exception_message' => $generation['exception_message'],
                ],
                $generation,
            ];
        }

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
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkOutcomeKind(
        ClaimDefinition $claim,
        DialectRuntime $contracts,
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

        $generation = $this->generationAttempt($claim, $contracts, $parameters, $generation);
        if (!$generation['succeeded'] || !is_string($generation['sql'])) {
            return [
                false,
                'generation failed before probing the engine',
                [
                    'request' => $generation['request'],
                    'allowed_kinds' => $normalizedAllowedKinds,
                    'succeeded' => false,
                    'exception_class' => $generation['exception_class'],
                    'exception_message' => $generation['exception_message'],
                ],
                $generation,
            ];
        }

        $observation = $this->observeOutcome($claim, $generation, $probe, $policy);
        $kind = $observation['actual_kind'];
        $passed = isset($allowed[$kind]);

        return [
            $passed,
            $passed
                ? sprintf('observed outcome %s is allowed', $kind)
                : sprintf('observed outcome %s is not allowed', $kind),
            [
                'request' => $generation['request'],
                'allowed_kinds' => $normalizedAllowedKinds,
                'actual_kind' => $observation['actual_kind'],
                'accepted' => $observation['accepted'],
                'phase' => $observation['phase'],
                'sqlstate' => $observation['sqlstate'],
                'error_code' => $observation['error_code'],
                'error_message' => $observation['error_message'],
            ],
            $generation,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkOutcomePhase(
        ClaimDefinition $claim,
        DialectRuntime $contracts,
        array $parameters,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        $expectedPhase = $this->requirePhaseOption($evidence);

        $probe = $this->probes[$claim->dialect] ?? null;
        if ($probe === null) {
            throw new InvalidArgumentException(sprintf('No engine probe registered for dialect %s.', $claim->dialect));
        }

        $policy = $this->policies[$claim->dialect] ?? null;
        if ($policy === null) {
            throw new InvalidArgumentException(sprintf('No outcome policy registered for dialect %s.', $claim->dialect));
        }

        $generation = $this->generationAttempt($claim, $contracts, $parameters, $generation);
        if (!$generation['succeeded'] || !is_string($generation['sql'])) {
            return [
                false,
                'generation failed before probing the engine',
                [
                    'request' => $generation['request'],
                    'expected_phase' => $expectedPhase,
                    'succeeded' => false,
                    'exception_class' => $generation['exception_class'],
                    'exception_message' => $generation['exception_message'],
                ],
                $generation,
            ];
        }

        $observation = $this->observeOutcome($claim, $generation, $probe, $policy);
        $passed = $observation['phase'] === $expectedPhase;

        return [
            $passed,
            $passed
                ? sprintf('observed phase %s matches', $expectedPhase)
                : sprintf('observed phase %s does not match expected %s', $observation['phase'], $expectedPhase),
            [
                'request' => $generation['request'],
                'expected_phase' => $expectedPhase,
                'actual_phase' => $observation['phase'],
                'actual_kind' => $observation['actual_kind'],
                'accepted' => $observation['accepted'],
                'sqlstate' => $observation['sqlstate'],
                'error_code' => $observation['error_code'],
                'error_message' => $observation['error_message'],
            ],
            $generation,
        ];
    }

    /**
     * @param array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     sql: string,
     *     actual_kind: string,
     *     accepted: bool,
     *     phase: string,
     *     sqlstate: null|string,
     *     error_code: null|int,
     *     error_message: null|string
     * }
     */
    private function observeOutcome(
        ClaimDefinition $claim,
        array $generation,
        EngineProbe $probe,
        OutcomePolicy $policy,
    ): array {
        if ($this->outcomeObservation !== null && $this->outcomeObservation['sql'] === $generation['sql']) {
            return $this->outcomeObservation;
        }

        $probeResult = $probe->observe($generation['sql']);

        return $this->outcomeObservation = [
            'sql' => $generation['sql'],
            'actual_kind' => $policy->classify($probeResult)->value,
            'accepted' => $probeResult->accepted,
            'phase' => $probeResult->phase->value,
            'sqlstate' => $probeResult->sqlState,
            'error_code' => $probeResult->errorCode,
            'error_message' => $probeResult->message,
        ];
    }

    /**
     * @param array<string, scalar> $parameters
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * }
     */
    private function generationAttempt(
        ClaimDefinition $claim,
        DialectRuntime $contracts,
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
        $requestFacts = [
            'start_rule' => $request->startRule,
            'seed' => $request->seed,
            'max_depth' => $request->maxDepth,
        ];

        try {
            return [
                'request' => $requestFacts,
                'succeeded' => true,
                'sql' => $contracts->generate($request),
                'exception_class' => null,
                'exception_message' => null,
            ];
        } catch (Throwable $e) {
            return [
                'request' => $requestFacts,
                'succeeded' => false,
                'sql' => null,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ];
        }
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
    private function requireFailurePattern(array $parameters, EvidenceDefinition $evidence): string
    {
        $pattern = $evidence->options['pattern'] ?? null;
        if ($pattern === null) {
            $parameterName = $evidence->options['parameter'] ?? null;
            if (!is_string($parameterName) || $parameterName === '') {
                throw new InvalidArgumentException('generation.fails requires a pattern or parameter option.');
            }

            $pattern = $parameters[$parameterName] ?? null;
        }

        if (!is_string($pattern) || $pattern === '') {
            throw new InvalidArgumentException('generation.fails requires a non-empty pattern.');
        }

        if (@preg_match($pattern, '') === false) {
            throw new InvalidArgumentException(sprintf('generation.fails received an invalid pattern: %s', $pattern));
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

    private function grammarChecker(ClaimDefinition $claim, DialectRuntime $contracts): GrammarContractChecker
    {
        $key = $claim->dialect . ':' . $claim->subjectKind;

        return $this->grammarCheckers[$key] ??= new GrammarContractChecker(
            $this->grammar($claim, $contracts),
        );
    }

    private function grammar(ClaimDefinition $claim, DialectRuntime $contracts): Grammar
    {
        return match ($claim->subjectKind) {
            'snapshot' => $contracts->snapshot(),
            'grammar' => $contracts->supportedGrammar(),
            default => throw new InvalidArgumentException(sprintf('Subject kind %s is not a grammar subject.', $claim->subjectKind)),
        };
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
     * @return array<string, int>
     */
    private function requireLengthMapOption(EvidenceDefinition $evidence, string $key): array
    {
        $values = $evidence->options[$key] ?? null;
        if (!is_array($values) || $values === []) {
            throw new InvalidArgumentException(sprintf('Evidence kind %s requires a non-empty %s map.', $evidence->kind, $key));
        }

        $result = [];
        foreach ($values as $rule => $length) {
            if (!is_string($rule) || $rule === '') {
                throw new InvalidArgumentException(sprintf('Evidence kind %s requires %s keys to be non-empty strings.', $evidence->kind, $key));
            }

            if (!is_int($length) || $length < 0) {
                throw new InvalidArgumentException(sprintf('Evidence kind %s requires %s values to be non-negative integers.', $evidence->kind, $key));
            }

            $result[$rule] = $length;
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
     * @param null|array{
     *     request: array<string, scalar|null>,
     *     succeeded: bool,
     *     sql: null|string,
     *     exception_class: null|string,
     *     exception_message: null|string
     * } $generation
     * @return array{
     *     bool,
     *     string,
     *     array<string, mixed>,
     *     null|array{
     *         request: array<string, scalar|null>,
     *         succeeded: bool,
     *         sql: null|string,
     *         exception_class: null|string,
     *         exception_message: null|string
     *     }
     * }
     */
    private function checkGrammarFingerprint(
        ClaimDefinition $claim,
        DialectRuntime $runtime,
        EvidenceDefinition $evidence,
        ?array $generation,
    ): array {
        $grammar = $this->grammar($claim, $runtime);
        $actual = GrammarFingerprint::sha256($grammar);
        $expected = $this->expectedFingerprint($runtime, $evidence);
        $matched = $actual === $expected;

        return [
            $matched,
            $matched ? 'grammar fingerprint matches' : 'grammar fingerprint does not match',
            [
                'subject_kind' => $claim->subjectKind,
                'version' => $runtime->version(),
                'expected_sha256' => $expected,
                'actual_sha256' => $actual,
                'matched' => $matched,
            ],
            $generation,
        ];
    }

    private function expectedFingerprint(DialectRuntime $runtime, EvidenceDefinition $evidence): string
    {
        $sha256 = $evidence->options['sha256'] ?? null;
        if (is_string($sha256) && $sha256 !== '') {
            return $sha256;
        }

        $sha256ByVersion = $evidence->options['sha256_by_version'] ?? null;
        if (!is_array($sha256ByVersion)) {
            throw new InvalidArgumentException('grammar.fingerprint_matches requires sha256 or sha256_by_version.');
        }

        $version = $runtime->version();
        if ($version === '') {
            throw new InvalidArgumentException('grammar.fingerprint_matches requires a runtime version when sha256_by_version is used.');
        }

        $resolved = $sha256ByVersion[$version] ?? null;
        if (!is_string($resolved) || $resolved === '') {
            throw new InvalidArgumentException(sprintf('No fingerprint configured for runtime version %s.', $version));
        }

        return $resolved;
    }

    private function requirePhaseOption(EvidenceDefinition $evidence): string
    {
        $phase = $evidence->options['phase'] ?? null;
        if (!is_string($phase) || $phase === '') {
            throw new InvalidArgumentException('outcome.phase_is requires a non-empty phase option.');
        }

        if (!in_array($phase, ['none', 'prepare', 'execute'], true)) {
            throw new InvalidArgumentException(sprintf('outcome.phase_is has unsupported phase: %s', $phase));
        }

        return $phase;
    }

    /**
     * @param array{total: int, passed: int, failed: int} $summary
     */
    private function statusFromSummary(array $summary): string
    {
        return $summary['failed'] === 0 ? 'passed' : 'failed';
    }
}
