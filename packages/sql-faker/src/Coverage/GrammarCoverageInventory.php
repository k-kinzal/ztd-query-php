<?php

declare(strict_types=1);

namespace SqlFaker\Coverage;

use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Symbol;

/**
 * Enumerates the effective grammar before any per-generation restrictions.
 *
 * @phpstan-type Entry array{rule: string, ordinal: int, origin: string|null, rhs: list<string>, rootReachable: bool}
 * @visibility root
 */
final class GrammarCoverageInventory
{
    /**
     * Identity of the grammar, adapter and lexical profile.
     */
    public readonly string $fingerprint;
    /**
     * Digest of the full reconstructed production inventory.
     */
    public readonly string $digest;
    /**
     * @var array<string, Entry>
     */
    public readonly array $entries;
    /**
     * @var list<string>
     */
    public readonly array $denominator;
    /**
     * @var list<array{origin: string, status: string}>
     */
    public readonly array $adaptations;

    /**
     * Includes inaccessible alternatives and records source grammar changes separately.
     */
    public function __construct(
        public readonly Grammar $grammar,
        public readonly string $root,
        string $profile,
        ?Grammar $original = null
    ) {
        $this->fingerprint = hash('sha256', $profile . ':' . $root . ':' . serialize($grammar));
        $reachable = $this->reachableRules();
        $entries = [];
        foreach ($grammar->ruleMap as $name => $rule) {
            foreach ($rule->alternatives as $ordinal => $production) {
                $entries[$this->id($name, $production, $ordinal)] = [
                    'rule' => $name, 'ordinal' => $production->ordinal ?? $ordinal,
                    'origin' => $production->origin, 'rhs' => self::rhs($production),
                    'rootReachable' => isset($reachable[$name]),
                ];
            }
        }
        $this->entries = $entries;
        $this->denominator = array_keys(array_filter($entries, static fn (array $entry): bool => $entry['rootReachable']));
        $this->digest = hash('sha256', serialize($entries));
        $this->adaptations = $this->differences($original);
    }

    /**
     * Keeps original ordinals and distinguishes rewritten right-hand sides.
     */
    public function id(string $rule, Production $production, int $fallbackOrdinal): string
    {
        return $this->fingerprint . ':' . $rule . '#' . ($production->ordinal ?? $fallbackOrdinal)
            . ':' . hash('sha256', serialize(self::rhs($production)));
    }

    /**
     * Describes symbols with their terminal/non-terminal distinction intact.
     *
     * @return list<string>
     */
    public static function rhs(Production $production): array
    {
        return array_map(static fn (Symbol $symbol): string =>
            ($symbol instanceof NonTerminal ? 'N:' : 'T:') . $symbol->value(), $production->symbols);
    }

    /**
     * Visits every alternative, including empty and lexically unsupported ones.
     *
     * @return array<string, true>
     */
    public function reachableRules(?string $root = null): array
    {
        $pending = [$root ?? $this->root];
        $visited = [];
        while ($pending !== []) {
            $name = array_pop($pending);
            if (isset($visited[$name])) {
                continue;
            }
            $visited[$name] = true;
            foreach ($this->grammar->ruleMap[$name]->alternatives ?? [] as $production) {
                array_push($pending, ...$production->nonTerminalNames());
            }
        }
        return $visited;
    }

    /**
     * Lists original alternatives removed or transformed by a fixed adapter.
     *
     * @return list<array{origin: string, status: string}>
     */
    public function differences(?Grammar $original): array
    {
        $changes = [];
        foreach ($original->ruleMap ?? [] as $name => $rule) {
            foreach ($rule->alternatives as $ordinal => $production) {
                $matches = array_filter($this->entries, static fn (array $entry): bool =>
                    $entry['rule'] === $name && $entry['ordinal'] === ($production->ordinal ?? $ordinal));
                $same = array_filter($matches, static fn (array $entry): bool => $entry['rhs'] === self::rhs($production));
                if ($same === []) {
                    $changes[] = ['origin' => $name . '#' . $ordinal, 'status' => $matches === [] ? 'excluded' : 'transformed'];
                }
            }
        }
        return $changes;
    }
}
