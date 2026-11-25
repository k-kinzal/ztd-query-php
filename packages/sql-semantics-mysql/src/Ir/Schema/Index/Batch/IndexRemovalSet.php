<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Batch;

use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;

/**
 * Closes an ordered removal group against the settled native namespace and physical policy.
 * @visibility SqlSemantics
 */
final class IndexRemovalSet
{
    /**
     * Checks the whole native request, including any generating-field dependency.
     * @param non-empty-list<IndexIdentity> $targets Physical keys in the chosen order
     */
    public function check(array $targets, IndexChangePolicy $policy): void
    {
        $owner = $targets[0]->owner;
        $expected = $this->coordinate($owner);
        $case = new NativeIdentifierCase();
        $removed = [];
        $clustered = 0;
        foreach ($targets as $target) {
            Check::input($this->coordinate($target->owner) === $expected, 'An atomic index removal requires one owning table and lifetime.');
            $name = $case->fold($target->name->value);
            Check::input(!isset($removed[$name]), 'An atomic index removal cannot repeat a physical key.');
            $removed[$name] = true;
            $clustered += (int) $target->role->clustered();
        }
        Check::input($clustered <= 1, 'One table supplies at most one clustered index target.');
        Check::input($policy->supports($owner->kind, $clustered === 1), 'The algorithm and lock cannot remove these native InnoDB indexes.');
        (new AutoIncrementRemovalSet())->check($targets, $removed);
    }

    /**
     * Extracts the typed external coordinate of the one owning table.
     * @return array{string, string, \SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind}
     */
    public function coordinate(TableIdentity $owner): array
    {
        return [$owner->name->schema->value, $owner->name->table->value, $owner->kind];
    }
}
