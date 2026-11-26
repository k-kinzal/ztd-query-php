<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Change;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexForeignKeyRequirement;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * An atomic sequence of index removals and renames on one MySQL table.
 * @visibility public
 * @example Reusing the name of a removed original key
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $catalog = $semantics->context(['CREATE TABLE app.t (a INT, b INT, KEY by_a(a), KEY by_b(b))']);
 *     count($semantics->analyze('ALTER TABLE app.t DROP INDEX by_b, RENAME INDEX by_a TO by_b', $catalog)->actions) // => 2
 */
final class AlterIndexes implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<IndexAlteration> Native index changes in written order
     */
    public readonly array $actions;

    /**
     * Closes the initial and final native namespaces of the mixed statement.
     * @param list<IndexAlteration> $actions Exact native index operations
     * @param list<IndexRemovalTarget> $removals Construction facts in the physical removal order
     * @param list<Identifier> $unmoved Original keys not named by the actions
     * @param list<IndexForeignKeyRequirement> $requirements Complete native foreign-key sides owning supporting physical keys
     */
    public function __construct(
        public readonly TableIdentity $owner,
        array $actions,
        array $removals,
        array $unmoved,
        array $requirements,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
        public readonly IndexChangePolicy $policy = new IndexChangePolicy(),
    ) {
        Check::input($release !== GrammarRelease::MySql5651, 'Mixed native index removal and renaming requires MySQL 5.7 or later.');
        $owned = Check::listOf($actions, IndexAlteration::class, 'A mixed index alteration requires an ordered list of two or more actions.', 2);
        $stable = Check::listOf($unmoved, Identifier::class, 'A mixed index alteration requires an ordered list of unmoved names.');
        $bound = Check::listOf($removals, IndexRemovalTarget::class, 'A mixed index alteration needs ordered original removal facts.');
        (new MixedIndexChanges())->check($owner, $owned, $bound, $stable, $policy, $requirements);
        $this->actions = $owned;
    }

    /**
     * Identifies the immutable native grammar and session profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/alter-indexes';
    }

    /**
     * Emits the complete checked native operation and its policy.
     */
    public function toString(): string
    {
        $clauses = [];
        foreach ($this->actions as $action) {
            $clauses[] = $action->toString();
        }

        return 'ALTER TABLE ' . $this->owner->quoted() . ' ' . implode(', ', $clauses) . $this->policy->sql(true);
    }
}
