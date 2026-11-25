<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalSet;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\IndexNameAllocation;

/**
 * Settles the physical keys created or replaced by a native foreign-key addition group.
 * @visibility SqlSemantics
 */
final class ForeignKeyChildIndexes
{
    /**
     * Returns the introduced key per addition and the replaced names in original catalog order.
     * @param list<ForeignKeyNativeKey> $catalog Original native keys with their full prefix
     * @param non-empty-list<non-empty-list<ForeignKeyPair>> $fields Resolved fields by action
     * @param non-empty-list<Identifier|null> $labels Effective explicit key-name requests by action
     * @return array{non-empty-list<Identifier|null>, list<Identifier>}
     */
    public function allocate(TableIdentity $owner, array $catalog, array $fields, array $labels, GrammarRelease $release): array
    {
        $layout = new ForeignKeyIndexPrefix();
        $occupied = $layout->keys($owner, $catalog);
        Check::input(count($occupied) <= 64, 'The original child catalog exceeds the native index limit.');
        $prefixes = $existing = [];
        foreach ($fields as $source) {
            $prefixes[] = $layout->child($source);
        }
        foreach ($occupied as $key) {
            if ($key->referenceKey) {
                $existing[] = $layout->physical($key);
            }
        }
        $introduced = [];
        foreach ($prefixes as $position => $prefix) {
            if (!$this->covered($position, $prefixes, $existing)) {
                $introduced[$position] = $prefix;
            }
        }
        $replaced = $this->replaced($occupied, $introduced, $layout);
        $used = [];
        foreach (array_keys($occupied) as $name) {
            if (!isset($replaced[$name])) {
                $used[$name] = true;
            }
        }
        $legacy = (new ForeignKeyRemovalSet())->legacy($release);
        $case = new NativeIdentifierCase();
        $allocator = new IndexNameAllocation();
        $result = [];
        foreach ($fields as $position => $pairs) {
            $label = $labels[$position];
            Check::input(!$legacy || $label === null || $case->fold($label->value) !== 'primary', 'The legacy foreign-key index label cannot be PRIMARY.');
            if (!isset($introduced[$position])) {
                $result[] = null;

                continue;
            }
            $name = $label->value ?? $allocator->available($pairs[0]->child->value, $used, $release);
            Check::input($name !== null && preg_match('/\A.{1,64}(?<! )\z/us', $name) === 1, 'An introduced foreign-key index needs a valid physical name.');
            $key = $case->fold($name);
            Check::input($key !== 'primary' && !isset($used[$key]), 'An introduced foreign-key index needs an available physical name.');
            $used[$key] = true;
            $result[] = new Identifier($name);
        }
        Check::input(count($used) <= 64, 'Native foreign-key additions must fit the physical index limit.');

        return [$result, array_values($replaced)];
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
     * @param list<list<array{string, bool}>> $existing Prior full leading child keys
     */
    public function covered(int $position, array $proposed, array $existing): bool
    {
        $wanted = $proposed[$position];
        $width = count($wanted);
        foreach ($existing as $key) {
            if (array_slice($key, 0, $width) === $wanted) {
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
