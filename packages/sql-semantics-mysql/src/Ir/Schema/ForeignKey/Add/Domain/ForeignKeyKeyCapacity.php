<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain;

use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyPair;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Contract\InnoDbKeyLimit;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Validates the per-field and combined full-key capacity of the native scope.
 * @visibility SqlSemantics
 */
final class ForeignKeyKeyCapacity
{
    /**
     * Selects the stock limit for a known physical layout.
     */
    public function effective(GrammarRelease $release, ?InnoDbKeyLimit $layout): InnoDbKeyLimit
    {
        $legacy = $release === GrammarRelease::MySql5651;
        Check::input(!$legacy || $layout !== InnoDbKeyLimit::LargePrefix, 'Stock MySQL 5.6 uses the compact native index limit.');

        return $layout ?? InnoDbKeyLimit::stock($release);
    }

    /**
     * Checks every full term independently and their combined native length.
     * @param non-empty-list<ForeignKeyPair> $fields The ordered full child key fields
     */
    public function check(array $fields, InnoDbKeyLimit $limit): void
    {
        $bytes = 0;
        foreach ($fields as $pair) {
            $term = $pair->domain->childKeyBytes();
            Check::input($term <= $limit->bytes(), 'The full foreign-key field exceeds the native InnoDB index field limit.');
            $bytes += $term;
        }
        Check::input($bytes <= 3072, 'The full foreign-key prefix exceeds the native InnoDB index byte limit.');
    }
}
