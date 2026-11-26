<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Index;

use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;

/**
 * Checks an ordered native field sequence against the physical key used for enforcement.
 * @visibility SqlSemantics
 */
final class IndexKeyCoverage
{
    /**
     * Checks the exact unique key or the native leading subsequence.
     * @param list<Name> $fields Ordered columns required by the constraint
     */
    public function covers(array $fields, IndexDeclaration $key, ?IndexDeclaration $cluster, bool $exactUnique): bool
    {
        if ($fields === [] || $key->kind === PhysicalIndexKind::FullText || $key->kind === PhysicalIndexKind::Spatial) {
            return false;
        }
        $comparison = new NativeIdentifierCase();
        $fold = static fn (Name $name): string => $comparison->fold($name->value);
        $leading = array_map($fold, $key->leadingFields);
        $expected = array_map($fold, $fields);
        if ($exactUnique) {
            return $key->kind !== PhysicalIndexKind::Index && $key->wholeFields && $leading === $expected;
        }
        if ($cluster !== null && $cluster !== $key && $key->wholeFields) {
            foreach (array_map($fold, $cluster->leadingFields) as $hidden) {
                if (!in_array($hidden, $leading, true)) {
                    $leading[] = $hidden;
                }
            }
        }

        return array_slice($leading, 0, count($expected)) === $expected;
    }

    /**
     * Selects the primary or the first eligible unique clustered key.
     * @param list<IndexDeclaration> $indexes Remaining physical definitions in declaration order
     */
    public function cluster(array $indexes): ?IndexDeclaration
    {
        $unique = null;
        foreach ($indexes as $key) {
            if ($key->kind === PhysicalIndexKind::Primary) {
                return $key;
            }
            if ($key->clusterCandidate) {
                $unique ??= $key;
            }
        }

        return $unique;
    }
}
