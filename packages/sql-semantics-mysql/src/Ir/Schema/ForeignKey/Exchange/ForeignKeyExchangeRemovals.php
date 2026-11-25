<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange;

use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Checks removal coordinates against the original physical owner.
 * @visibility SqlSemantics
 */
final class ForeignKeyExchangeRemovals
{
    /**
     * Requires each resolved removal name to identify an original constraint on this table.
     * @param non-empty-list<ForeignKeyRemoval> $removals The exact physical removal roles
     * @param list<ForeignKeyConstraintIdentity> $original The original database constraint namespace
     */
    public function check(TableIdentity $owner, array $removals, array $original): void
    {
        $origin = $owner->quoted();
        $names = [];
        foreach ($original as $constraint) {
            if ($constraint->owner->quoted() === $origin) {
                $names[$constraint->symbol->value] = true;
            }
        }
        foreach ($removals as $removal) {
            Check::input(
                $removal->name !== null && isset($names[$removal->name->value]),
                'The exchange removal must select an original native constraint on its owning table.',
            );
        }
    }
}
