<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyNativeKey;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAddition;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAdditionSet;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalSet;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Contract\InnoDbKeyLimit;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * One native ALTER TABLE that removes and introduces resolved foreign keys.
 * @visibility public
 * @example Replacing a foreign constraint with another name
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $catalog = $semantics->context(['CREATE TABLE app.p (id INT PRIMARY KEY)', 'CREATE TABLE app.c (pid INT, CONSTRAINT before_fk FOREIGN KEY (pid) REFERENCES p(id))']);
 *     $semantics->analyze('ALTER TABLE app.c DROP FOREIGN KEY before_fk, ADD CONSTRAINT after_fk FOREIGN KEY (pid) REFERENCES p(id)', $catalog)->actions[0]->toString() // => 'DROP FOREIGN KEY `before_fk`'
 */
final class AlterForeignKeys implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<ForeignKeyAddition|ForeignKeyRemoval> Checked clauses in their native order
     */
    public readonly array $actions;

    /**
     * @var list<Identifier> Prior implicit indexes replaced by the added physical keys
     */
    public readonly array $removedIndexes;

    /**
     * Checks both edit kinds against the starting schema before publishing the atomic operation.
     *
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $actions The ordered foreign-key edits
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $keys The original physical key catalog
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $constraints The original constraint catalog
     */
    public function __construct(
        public readonly TableIdentity $owner,
        array $actions,
        array $keys,
        array $constraints,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
        public readonly IndexChangePolicy $policy = new IndexChangePolicy(),
        public readonly bool $ignore = false,
        ?InnoDbKeyLimit $keyLimit = null,
    ) {
        Check::input(array_is_list($actions) && $actions !== [], 'A foreign-key exchange needs a nonempty ordered action list.');
        $checked = $additions = $removals = [];
        foreach ($actions as $action) {
            Check::input($action instanceof ForeignKeyAddition || $action instanceof ForeignKeyRemoval, 'A foreign-key exchange requires checked addition and removal roles.');
            $checked[] = $action;
            if ($action instanceof ForeignKeyAddition) {
                $additions[] = $action;
            } else {
                $removals[] = $action;
            }
        }
        Check::input($additions !== [] && $removals !== [], 'A foreign-key exchange requires both additions and removals.');
        $originalKeys = Check::listOf($keys, ForeignKeyNativeKey::class, 'Foreign-key exchange requires checked original physical keys.');
        $originalConstraints = Check::listOf($constraints, ForeignKeyConstraintIdentity::class, 'Foreign-key exchange requires checked original symbols.');
        (new ForeignKeyRemovalSet())->check($owner, $removals, [], $release, $policy);
        (new ForeignKeyExchangeRemovals())->check($owner, $removals, $originalConstraints);
        $this->removedIndexes = (new ForeignKeyAdditionSet())->check($owner, $additions, $originalKeys, $originalConstraints, $release, $policy, $ignore, $keyLimit);
        $this->actions = $checked;
    }

    /**
     * Identifies the fixed native InnoDB exchange interpretation.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/alter-foreign-keys';
    }

    /**
     * Emits the resolved group under its one native algorithm and lock request.
     */
    public function toString(): string
    {
        $verb = $this->ignore ? 'ALTER IGNORE TABLE ' : 'ALTER TABLE ';
        $actions = [];
        foreach ($this->actions as $action) {
            $actions[] = $action->toString();
        }

        return $verb . $this->owner->quoted() . ' ' . implode(', ', $actions) . $this->policy->sql(true);
    }
}
