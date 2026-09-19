<?php

declare(strict_types=1);

namespace SqlCatalog\Conformance;

use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Placeholder;

/**
 * Checks a catalog against the statements a program actually sent.
 *
 * A static analysis of what SQL an application issues is only worth anything if
 * it does not miss statements the application really issues. Running the same
 * code and comparing what the driver saw with what the catalog says is the one
 * check that can establish that, rather than assert it.
 *
 * @visibility public
 * @example Checking a catalog against one statement the program sent
 *     $catalog = (new \SqlCatalog\Analyzer())->analyzeSource([
 *         'a.php' => '<?php function f(PDO $d) { $d->query("SELECT 1"); }',
 *     ]);
 *     $observed = [new \SqlCatalog\Conformance\ObservedStatement('SELECT 1')];
 *     (new \SqlCatalog\Conformance\ConformanceChecker())->check($catalog, $observed)->isSound() // => true
 */
final class ConformanceChecker
{
    private PatternMatcher $matcher;

    /**
     * Builds a checker over the shape matcher.
     */
    public function __construct(?PatternMatcher $matcher = null)
    {
        $this->matcher = $matcher ?? new PatternMatcher();
    }

    /**
     * How the catalog held up against what the program sent.
     *
     * @param list<ObservedStatement> $observations
     */
    public function check(Catalog $catalog, array $observations): ConformanceReport
    {
        $covered = 0;
        $resolved = 0;
        $uncovered = [];
        $mismatches = [];

        foreach ($observations as $observation) {
            $matches = $this->matching($catalog, $observation);
            if ($matches === []) {
                $uncovered[] = $observation;
                continue;
            }
            $covered++;
            $resolved += $this->hasResolved($matches) ? 1 : 0;
            foreach ($this->valueMismatches($matches, $observation) as $mismatch) {
                $mismatches[] = $mismatch;
            }
        }

        return new ConformanceReport($observations === [] ? 0 : count($observations), $covered, $resolved, $uncovered, $mismatches);
    }

    /**
     * The catalogued statements that describe what the program sent.
     *
     * @return list<CatalogEntry>
     */
    public function matching(Catalog $catalog, ObservedStatement $observation): array
    {
        $matches = [];
        foreach ($catalog as $entry) {
            if ($this->matcher->matches($entry->pattern, $observation->sql)) {
                $matches[] = $entry;
            }
        }

        return $matches;
    }

    /**
     * Whether one of the matches has no gaps left in it.
     *
     * @param list<CatalogEntry> $matches
     */
    public function hasResolved(array $matches): bool
    {
        foreach ($matches as $match) {
            if ($match->isExact()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The values no matching statement admits.
     *
     * A value is only reported when every statement that matches rejects it,
     * because the program took one of those paths and only one has to allow it.
     *
     * @param list<CatalogEntry> $matches
     * @return list<string>
     */
    public function valueMismatches(array $matches, ObservedStatement $observation): array
    {
        $mismatches = [];
        foreach ($this->boundValues($observation) as $key => $value) {
            if (!$this->anyAdmits($matches, (string) $key, $value)) {
                $mismatches[] = sprintf(
                    '%s: parameter %s was given %s, which no matching statement admits.',
                    $observation->source === '' ? $observation->normalized() : $observation->source,
                    (string) $key,
                    var_export($value, true),
                );
            }
        }

        return $mismatches;
    }

    /**
     * The values the program bound, keyed the way a placeholder is keyed.
     *
     * @return array<string|int, string|int|float|bool|null>
     */
    public function boundValues(ObservedStatement $observation): array
    {
        $values = [];
        foreach ($observation->positional as $position => $value) {
            $values[$position] = $value;
        }
        foreach ($observation->named as $name => $value) {
            $values[ltrim($name, ':')] = $value;
        }

        return $values;
    }

    /**
     * Whether one of the matching statements admits the value at that key.
     *
     * @param list<CatalogEntry> $matches
     */
    public function anyAdmits(array $matches, string $key, string|int|float|bool|null $value): bool
    {
        $found = false;
        foreach ($matches as $match) {
            foreach ($match->placeholders as $placeholder) {
                if (!$this->isKeyed($placeholder, $key)) {
                    continue;
                }
                $found = true;
                if ($placeholder->value === null || $placeholder->value->admits($value)) {
                    return true;
                }
            }
        }

        return !$found;
    }

    /**
     * Whether a parameter is the one a bound value is keyed under.
     */
    public function isKeyed(Placeholder $placeholder, string $key): bool
    {
        return $placeholder->key() === $key || (string) $placeholder->position === $key;
    }
}
