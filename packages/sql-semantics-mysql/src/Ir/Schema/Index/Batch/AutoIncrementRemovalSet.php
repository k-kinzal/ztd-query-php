<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Batch;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;

/**
 * Checks backing-index retention when multiple keys on one table are removed together.
 * @visibility SqlSemantics
 */
final class AutoIncrementRemovalSet
{
    /**
     * Reconciles the shared support set and requires a distinct survivor.
     * @param non-empty-list<IndexRemovalTarget> $targets Physical keys in the chosen order
     * @param array<array-key, true> $removed Removed physical names after native case folding
     * @param array<array-key, true> $introduced Leading full fields of newly allocated keys
     */
    public function check(array $targets, array $removed, array $introduced = []): void
    {
        $case = new NativeIdentifierCase();
        $backing = null;
        $field = null;
        foreach ($targets as $target) {
            $increment = $target->autoIncrement;
            if ($increment === null) {
                continue;
            }
            $currentField = $case->fold($increment->field->value);
            $current = [$case->fold($target->index->name->value) => true];
            foreach ($increment->alternates as $alternate) {
                $current[$case->fold($alternate->value)] = true;
            }
            ksort($current, SORT_STRING);
            Check::input(
                $field === null || ($currentField === $field && $current === $backing),
                'Removed AUTO_INCREMENT backing indexes must agree on one field and its complete backing set.'
            );
            $field = $currentField;
            $backing = $current;
        }
        if ($backing === null) {
            return;
        }
        foreach ($targets as $target) {
            $included = isset($backing[$case->fold($target->index->name->value)]);
            Check::input($included === ($target->autoIncrement !== null), 'Each removed backing key must identify the same AUTO_INCREMENT dependence.');
        }
        Check::invariant($field !== null, 'A generating backup set belongs to one resolved field.');
        Check::input(
            array_diff_key($backing, $removed) !== [] || isset($introduced[$field]),
            'Atomic removal must retain a backing key for the AUTO_INCREMENT field.'
        );
    }
}
