<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyKeyColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\PhysicalKeyNames;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalSet;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;

/**
 * Settles the physical keys created or replaced by a native foreign-key addition group.
 * @visibility SqlSemantics
 * @phpstan-type ExplicitIndexRequest array{Identifier|null, non-empty-list<ForeignKeyKeyColumn>}
 */
final class ForeignKeyChildIndexes
{
    /**
     * Returns the introduced key per addition and the replaced names in original catalog order.
     * @param list<ForeignKeyNativeKey> $catalog Original native keys with their full prefix
     * @param non-empty-list<non-empty-list<ForeignKeyPair>> $fields Resolved fields by action
     * @param non-empty-list<Identifier|null> $labels Effective explicit key-name requests by action
     * @param array<int, ExplicitIndexRequest> $explicit Checked native explicit index requests keyed by written action slot
     * @param list<int> $foreignSlots Written action slot of each addition, or empty for a pure group
     * @return array{non-empty-list<Identifier|null>, list<Identifier>, array<int, Identifier>}
     */
    public function allocate(
        TableIdentity $owner,
        array $catalog,
        array $fields,
        array $labels,
        GrammarRelease $release,
        array $explicit = [],
        array $foreignSlots = [],
    ): array {
        $layout = new ForeignKeyIndexPrefix();
        $occupied = $layout->keys($owner, $catalog);
        Check::input(count($occupied) <= 64, 'The original child catalog exceeds the native index limit.');
        $prefixes = $existing = $added = [];
        foreach ($fields as $source) {
            $prefixes[] = $layout->child($source);
        }
        foreach ($occupied as $key) {
            if ($key->referenceKey) {
                $existing[] = [$layout->physical($key), $key->implicitForeignKey];
            }
        }
        foreach ($explicit as [, $columns]) {
            $prefix = $layout->columns($columns);
            Check::invariant($prefix !== [], 'A selected explicit key has at least one whole field.');
            $existing[] = [$prefix, false];
            $added[] = $prefix;
        }
        $introduced = [];
        foreach ($prefixes as $position => $prefix) {
            if (!$this->covered($position, $prefixes, $existing)) {
                $introduced[$position] = $prefix;
            }
        }
        $replaced = $this->replaced($occupied, [...$introduced, ...$added], $layout);
        $slots = $foreignSlots === [] ? array_keys($fields) : $foreignSlots;
        $requests = $this->requests($fields, $labels, $introduced, $explicit, $slots, $release);
        $physical = (new PhysicalKeyNames())->allocate($occupied, $replaced, $requests, $release);
        $result = [];
        foreach ($slots as $slot) {
            $result[] = $physical[$slot] ?? null;
        }

        return [$result, array_values($replaced), array_intersect_key($physical, $explicit)];
    }

    /**
     * Prepares only the effective new physical names in their original combined action positions.
     * @param non-empty-list<non-empty-list<ForeignKeyPair>> $fields Resolved fields by action
     * @param non-empty-list<Identifier|null> $labels Effective native foreign-key label requests
     * @param array<int, non-empty-list<array{string, bool}>> $introduced Physical prefixes required by new constraints
     * @param array<int, ExplicitIndexRequest> $explicit Written physical additions keyed by action slot
     * @param list<int> $slots The corresponding action positions of foreign keys
     * @return array<int, array{Identifier, Identifier|null}>
     */
    public function requests(array $fields, array $labels, array $introduced, array $explicit, array $slots, GrammarRelease $release): array
    {
        $legacy = (new ForeignKeyRemovalSet())->legacy($release);
        $case = new NativeIdentifierCase();
        $requests = [];
        foreach ($explicit as $slot => [$label, $columns]) {
            $requests[$slot] = [$columns[0]->field, $label];
        }
        foreach ($fields as $position => $pairs) {
            $label = $labels[$position];
            Check::input(!$legacy || $label === null || $case->fold($label->value) !== 'primary', 'The legacy foreign-key index label cannot be PRIMARY.');
            if (isset($introduced[$position])) {
                $requests[$slots[$position]] = [$pairs[0]->child, $label];
            }
        }

        return $requests;
    }

    /**
     * Finds generated child indexes covered by a newly introduced physical key.
     *
     * @param array<array-key, ForeignKeyNativeKey> $original Original keys of this child
     * @param array<int, non-empty-list<array{string, bool}>> $introduced Effective new directional prefixes
     * @return array<array-key, Identifier>
     */
    public function replaced(array $original, array $introduced, ForeignKeyIndexPrefix $layout): array
    {
        $replacements = [];
        foreach ($original as $name => $key) {
            if (!$key->implicitForeignKey) {
                continue;
            }
            $prefix = $layout->physical($key);
            foreach ($introduced as $candidate) {
                if (array_slice($candidate, 0, count($prefix)) === $prefix) {
                    $replacements[$name] = $key->identity->name;

                    break;
                }
            }
        }

        return $replacements;
    }

    /**
     * Checks full ordered directional coverage by existing or retained generated keys.
     * @param non-empty-list<non-empty-list<array{string, bool}>> $proposed Checked directional prefixes
     * @param list<array{list<array{string, bool}>, bool}> $existing Prior leading child fields and implicit origin
     */
    public function covered(int $position, array $proposed, array $existing): bool
    {
        $wanted = $proposed[$position];
        $width = count($wanted);
        foreach ($existing as [$key, $implicit]) {
            if ((!$implicit || count($key) > $width) && array_slice($key, 0, $width) === $wanted) {
                return true;
            }
        }
        foreach ($proposed as $order => $candidate) {
            if (($order > $position || count($candidate) > $width) && array_slice($candidate, 0, $width) === $wanted) {
                return true;
            }
        }

        return false;
    }
}
