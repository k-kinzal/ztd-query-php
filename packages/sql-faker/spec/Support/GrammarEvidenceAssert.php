<?php

declare(strict_types=1);

namespace Spec\Support;

use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use Spec\Runner\GrammarContractChecker;
use SqlFaker\Contract\Grammar;

final class GrammarEvidenceAssert
{
    /**
     * @param array<string, mixed> $evidence
     */
    public static function assert(
        Grammar $grammar,
        GrammarContractChecker $checker,
        array $evidence,
        string $claimId,
    ): void {
        $kind = self::requireString($evidence, 'kind', $claimId);

        switch ($kind) {
            case 'grammar.no_undefined_references':
                Assert::assertSame([], $checker->undefinedReferences(), $claimId);
                return;

            case 'grammar.no_empty_rules':
                Assert::assertSame([], $checker->rulesWithoutAlternatives(), $claimId);
                return;

            case 'grammar.entries.present':
                Assert::assertSame([], $checker->missingEntries(self::requireStringList($evidence, 'entries', $claimId)), $claimId);
                return;

            case 'grammar.entries.terminate':
                Assert::assertSame([], $checker->nonTerminatingReachableRules(self::requireStringList($evidence, 'entries', $claimId)), $claimId);
                return;

            case 'grammar.rules.reachable':
                Assert::assertSame(
                    [],
                    $checker->unreachableRules(
                        self::requireStringList($evidence, 'entries', $claimId),
                        self::requireStringList($evidence, 'rules', $claimId),
                    ),
                    $claimId,
                );
                return;

            case 'grammar.rule.contains_sequence':
                self::assertRuleSequence($grammar, $evidence, $claimId, true);
                return;

            case 'grammar.rule.not_contains_sequence':
                self::assertRuleSequence($grammar, $evidence, $claimId, false);
                return;

            default:
                throw new InvalidArgumentException(sprintf('Unsupported grammar evidence kind: %s', $kind));
        }
    }

    /**
     * @param array<string, mixed> $evidence
     */
    private static function assertRuleSequence(Grammar $grammar, array $evidence, string $claimId, bool $shouldExist): void
    {
        $ruleName = self::requireString($evidence, 'rule', $claimId);
        $sequence = self::requireSequence($evidence, $claimId);
        $rule = $grammar->rule($ruleName);

        Assert::assertNotNull($rule, sprintf('%s: rule %s is missing', $claimId, $ruleName));

        $sequencePresent = false;
        foreach ($rule->alternatives as $alternative) {
            if ($alternative->sequence() === $sequence) {
                $sequencePresent = true;
                break;
            }
        }

        if ($shouldExist) {
            Assert::assertTrue(
                $sequencePresent,
                sprintf('%s: rule %s must contain %s', $claimId, $ruleName, implode(' ', $sequence)),
            );

            return;
        }

        Assert::assertFalse(
            $sequencePresent,
            sprintf('%s: rule %s must exclude %s', $claimId, $ruleName, implode(' ', $sequence)),
        );
    }

    /**
     * @param array<string, mixed> $evidence
     */
    private static function requireString(array $evidence, string $key, string $claimId): string
    {
        $value = $evidence[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf('Claim %s requires a non-empty string %s.', $claimId, $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $evidence
     * @return list<string>
     */
    private static function requireStringList(array $evidence, string $key, string $claimId): array
    {
        $values = $evidence[$key] ?? null;
        if (!is_array($values) || $values === []) {
            throw new InvalidArgumentException(sprintf('Claim %s requires a non-empty %s list.', $claimId, $key));
        }

        $result = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new InvalidArgumentException(sprintf('Claim %s contains a non-string %s entry.', $claimId, $key));
            }

            $result[] = $value;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $evidence
     * @return list<string>
     */
    private static function requireSequence(array $evidence, string $claimId): array
    {
        $sequence = self::requireStringList($evidence, 'sequence', $claimId);

        foreach ($sequence as $symbol) {
            if (!str_starts_with($symbol, 't:') && !str_starts_with($symbol, 'nt:')) {
                throw new InvalidArgumentException(sprintf('Claim %s uses an invalid grammar symbol prefix: %s', $claimId, $symbol));
            }
        }

        return $sequence;
    }
}
