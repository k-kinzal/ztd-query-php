<?php

declare(strict_types=1);

namespace Spec\Output;

final class HumanReadableRenderer
{
    /**
     * @param array<string, mixed> $report
     */
    public function render(array $report): string
    {
        $summary = $report['summary'] ?? [];
        $claims = $report['claims'] ?? [];
        if (!is_array($summary) || !is_array($claims)) {
            return "Spec Run: UNKNOWN\n";
        }

        $lines = [];
        $status = match ($summary['status'] ?? null) {
            'passed' => 'PASS',
            'failed' => 'FAIL',
            default => strtoupper($this->stringOr($summary['status'] ?? null, 'unknown')),
        };
        $lines[] = sprintf('Spec Run: %s', $status);

        $scopeParts = [];
        $scope = $summary['scope'] ?? [];
        if (is_array($scope)) {
            foreach (['command', 'level', 'dialect', 'mysql_version'] as $key) {
                $value = $scope[$key] ?? null;
                if (is_string($value) && $value !== '') {
                    $scopeParts[] = sprintf('%s=%s', $key, $value);
                }
            }
        }
        if ($scopeParts !== []) {
            $lines[] = 'Scope: ' . implode(' ', $scopeParts);
        }

        $lines[] = 'Claims: ' . $this->formatCountSummary($summary['claims'] ?? []);
        $lines[] = 'Cases: ' . $this->formatCountSummary($summary['cases'] ?? []);
        $lines[] = 'Checks: ' . $this->formatCountSummary($summary['checks'] ?? []);

        $failedClaims = [];
        $passedClaims = [];
        foreach ($claims as $claim) {
            if (!$this->isStringKeyedArray($claim)) {
                continue;
            }
            /** @var array<string, mixed> $claim */

            if (($claim['status'] ?? null) === 'failed') {
                $failedClaims[] = $claim;
                continue;
            }

            $passedClaims[] = $claim;
        }

        if ($failedClaims !== []) {
            $lines[] = '';
            $lines[] = 'Failures';
            $lines[] = '';

            foreach ($failedClaims as $index => $claim) {
                /** @var array<string, mixed> $claim */
                if ($index > 0) {
                    $lines[] = '';
                }

                $lines = [...$lines, ...$this->renderFailedClaim($claim, $index + 1)];
            }
        }

        if ($passedClaims !== []) {
            $lines[] = '';
            $lines[] = sprintf('Passed Claims (%d)', count($passedClaims));

            $includeStatements = $failedClaims === [];
            foreach ($passedClaims as $claim) {
                /** @var array<string, mixed> $claim */
                $claimId = $this->stringOr($claim['claim_id'] ?? null, 'unknown');
                $lines[] = '- ' . $claimId;

                if ($includeStatements) {
                    $statement = $this->stringOr($claim['statement'] ?? null, '');
                    if ($statement !== '') {
                        foreach ($this->indentBlock($statement, '  ') as $line) {
                            $lines[] = $line;
                        }
                    }
                }
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<string, mixed> $claim
     * @return list<string>
     */
    private function renderFailedClaim(array $claim, int $displayIndex): array
    {
        $lines = [];
        $lines[] = sprintf('%d. %s', $displayIndex, $this->stringOr($claim['claim_id'] ?? null, 'unknown'));

        $statement = $this->stringOr($claim['statement'] ?? null, '');
        if ($statement !== '') {
            $lines[] = '   Statement:';
            $lines = [...$lines, ...$this->indentBlock($statement, '   ')];
        }

        $source = $claim['source'] ?? [];
        if (is_array($source)) {
            $location = $this->stringOr($source['location'] ?? null, '');
            if ($location !== '') {
                $lines[] = '   Source: ' . $location;
            }
        }

        $cases = $claim['cases'] ?? [];
        if (!is_array($cases)) {
            return $lines;
        }

        foreach ($cases as $index => $case) {
            if (!$this->isStringKeyedArray($case)) {
                continue;
            }
            /** @var array<string, mixed> $case */
            if (($case['status'] ?? null) !== 'failed') {
                continue;
            }

            $lines[] = '';
            $caseNumber = is_int($case['case_number'] ?? null) ? $case['case_number'] : $index + 1;
            $lines[] = sprintf('   Failed Case %d', $caseNumber);
            $lines[] = '   - parameters: ' . $this->inlineJson($case['parameters'] ?? []);

            $generation = $case['generation'] ?? [];
            if (is_array($generation) && $generation !== []) {
                $request = $generation['request'] ?? null;
                if (is_array($request)) {
                    $lines[] = '   - generation request: ' . $this->inlineJson($request);
                }

                $sql = $this->stringOr($generation['sql'] ?? null, '');
                if ($sql !== '') {
                    $lines[] = '   - generated sql:';
                    $lines = [...$lines, ...$this->indentBlock($sql, '     ')];
                }
            }

            $checks = $case['checks'] ?? [];
            if (!is_array($checks)) {
                continue;
            }

            foreach ($checks as $check) {
                if (!$this->isStringKeyedArray($check)) {
                    continue;
                }
                /** @var array<string, mixed> $check */
                if (($check['status'] ?? null) !== 'failed') {
                    continue;
                }

                $lines = [...$lines, ...$this->renderFailedCheck($check)];
            }
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $check
     * @return list<string>
     */
    private function renderFailedCheck(array $check): array
    {
        $kind = $this->stringOr($check['kind'] ?? null, 'unknown');
        $message = $this->stringOr($check['message'] ?? null, $this->stringOr($check['details'] ?? null, 'check failed'));

        $lines = [];
        $lines[] = sprintf('   - %s: %s', $kind, $message);

        $facts = $check['facts'] ?? [];
        if (!$this->isStringKeyedArray($facts)) {
            return $lines;
        }
        /** @var array<string, mixed> $typedFacts */
        $typedFacts = $facts;

        foreach ($this->formatFacts($kind, $typedFacts) as $line) {
            $lines[] = '     ' . $line;
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $facts
     * @return list<string>
     */
    private function formatFacts(string $kind, array $facts): array
    {
        return match ($kind) {
            'outcome.kind_in' => $this->formatOutcomeFacts($facts),
            'generation.sql_matches' => $this->formatGenerationMatchFacts($facts),
            'grammar.rule.contains_sequence', 'grammar.rule.not_contains_sequence' => $this->formatRuleSequenceFacts($facts),
            'grammar.no_undefined_references' => $this->formatListFacts('undefined references', $facts['undefined_references'] ?? []),
            'grammar.no_empty_rules' => $this->formatListFacts('empty rules', $facts['rules'] ?? []),
            'grammar.entries.present' => $this->formatListFacts('missing entries', $facts['missing_entries'] ?? []),
            'grammar.entries.terminate' => $this->formatListFacts('non-terminating rules', $facts['non_terminating_rules'] ?? []),
            'grammar.rules.reachable' => $this->formatListFacts('unreachable rules', $facts['unreachable_rules'] ?? []),
            default => $facts === [] ? [] : ['facts: ' . $this->inlineJson($facts)],
        };
    }

    /**
     * @param array<string, mixed> $facts
     * @return list<string>
     */
    private function formatOutcomeFacts(array $facts): array
    {
        $lines = [];
        $allowedKinds = $facts['allowed_kinds'] ?? [];
        if (is_array($allowedKinds) && $allowedKinds !== []) {
            $lines[] = 'expected: ' . implode(', ', array_map(static fn (mixed $kind): string => is_string($kind) ? $kind : 'unknown', $allowedKinds));
        }

        $actualKind = $this->stringOr($facts['actual_kind'] ?? null, '');
        if ($actualKind !== '') {
            $lines[] = 'actual: ' . $actualKind;
        }

        foreach ([
            'phase' => 'phase',
            'sqlstate' => 'sqlstate',
            'error_code' => 'error_code',
            'error_message' => 'message',
        ] as $key => $label) {
            $value = $facts[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $lines[] = sprintf('%s: %s', $label, is_scalar($value) ? (string) $value : $this->inlineJson($value));
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $facts
     * @return list<string>
     */
    private function formatGenerationMatchFacts(array $facts): array
    {
        $lines = [];
        $pattern = $this->stringOr($facts['pattern'] ?? null, '');
        if ($pattern !== '') {
            $lines[] = 'pattern: ' . $pattern;
        }

        if (array_key_exists('matched', $facts)) {
            $lines[] = 'matched: ' . ($facts['matched'] === true ? 'yes' : 'no');
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $facts
     * @return list<string>
     */
    private function formatRuleSequenceFacts(array $facts): array
    {
        $lines = [];

        $rule = $this->stringOr($facts['rule'] ?? null, '');
        if ($rule !== '') {
            $lines[] = 'rule: ' . $rule;
        }

        $expectation = $this->stringOr($facts['expectation'] ?? null, '');
        if ($expectation !== '') {
            $lines[] = 'expectation: ' . $expectation;
        }

        if (array_key_exists('rule_found', $facts)) {
            $lines[] = 'rule found: ' . ($facts['rule_found'] === true ? 'yes' : 'no');
        }

        if (array_key_exists('matched', $facts)) {
            $lines[] = 'matched: ' . ($facts['matched'] === true ? 'yes' : 'no');
        }

        $sequence = $facts['expected_sequence'] ?? [];
        if (is_array($sequence) && $sequence !== []) {
            $lines[] = 'expected sequence: [' . implode(', ', array_map(static fn (mixed $symbol): string => is_string($symbol) ? $symbol : 'unknown', $sequence)) . ']';
        }

        return $lines;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function formatListFacts(string $label, mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            return [];
        }

        return [$label . ': ' . $this->inlineJson($value)];
    }

    /**
     * @param mixed $summary
     */
    private function formatCountSummary(mixed $summary): string
    {
        if (!is_array($summary)) {
            return '0 passed, 0 failed';
        }

        $passed = is_int($summary['passed'] ?? null) ? $summary['passed'] : 0;
        $failed = is_int($summary['failed'] ?? null) ? $summary['failed'] : 0;

        return sprintf('%d passed, %d failed', $passed, $failed);
    }

    /**
     * @return list<string>
     */
    private function indentBlock(string $text, string $prefix): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $text);
        if (!is_array($lines)) {
            return [$prefix . $text];
        }

        return array_map(static fn (string $line): string => $prefix . $line, $lines);
    }

    private function inlineJson(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function stringOr(mixed $value, string $fallback): string
    {
        return is_string($value) ? $value : $fallback;
    }

    private function isStringKeyedArray(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }
}
