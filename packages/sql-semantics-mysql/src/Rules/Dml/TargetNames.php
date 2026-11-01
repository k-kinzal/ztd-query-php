<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Identifies repeated DELETE operands independently of catalog state.
 *
 * Only identical qualified names are intrinsically repeated. Resolving an
 * omitted database or applying instance name comparison remains contextual.
 * Native observations: MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TargetNames
{
    /**
     * Returns the position of the first exact repetition, or null.
     *
     * @param list<QualifiedName> $targets The targets in written order
     */
    public function duplicate(array $targets): ?int
    {
        $seen = [];
        foreach ($targets as $position => $target) {
            $key = serialize([$target->catalog?->value, $target->schema?->value, $target->name->value]);
            if (isset($seen[$key])) {
                return $position;
            }
            $seen[$key] = true;
        }

        return null;
    }
}
