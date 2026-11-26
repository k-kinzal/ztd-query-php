<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency;

use SqlSemantics\Platform\MySql\Ir\Schema\Index\Batch\IndexRemovalSet;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;

/**
 * Checks foreign-key support across an entire native removal set.
 * @visibility SqlSemantics
 */
final class IndexDependencyClosure
{
    /**
     * Requires a survivor for every independently supplied native foreign-key side.
     * @param non-empty-list<IndexIdentity> $targets Exact indexes to remove
     * @param list<IndexForeignKeyRequirement> $requirements Complete affected owner dependencies
     */
    public function check(array $targets, array $requirements): void
    {
        $checked = Check::listOf($requirements, IndexForeignKeyRequirement::class, 'Index removal needs checked native foreign-key requirements.');
        $scope = new IndexRemovalSet();
        $owner = $scope->coordinate($targets[0]->owner);
        $case = new NativeIdentifierCase();
        $removed = [];
        foreach ($targets as $index) {
            $removed[$case->fold($index->name->value)] = true;
        }
        foreach ($checked as $requirement) {
            Check::input($scope->coordinate($requirement->owner) === $owner, 'A foreign-key index requirement must name the selected persistent owner.');
            $survives = false;
            foreach ($requirement->keys as $key) {
                $survives = $survives || !isset($removed[$case->fold($key->value)]);
            }
            Check::input($survives, 'Index removal must retain a supporting key for each native foreign-key side.');
        }
    }
}
