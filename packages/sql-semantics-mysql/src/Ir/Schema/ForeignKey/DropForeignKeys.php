<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey;

use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexForeignKeyRequirement;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Ordered native removal of foreign constraints and optional physical indexes.
 * @visibility public
 * @example Removing a resolved InnoDB foreign key
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $catalog = $semantics->context(['CREATE TABLE app.p (id INT PRIMARY KEY)', 'CREATE TABLE app.c (pid INT, CONSTRAINT parent_ref FOREIGN KEY (pid) REFERENCES p(id))']);
 *     $semantics->analyze('ALTER TABLE app.c DROP FOREIGN KEY parent_ref', $catalog)->toString() // => 'ALTER TABLE `app`.`c` DROP FOREIGN KEY `parent_ref`'
 */
final class DropForeignKeys implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<ForeignKeyRemoval|IndexIdentity> Native actions in written order
     */
    public readonly array $actions;

    /**
     * Fixes the native graph update and every remaining required key.
     * @param array<array-key, mixed> $actions Ordered checked actions on one physical owner
     * @param list<IndexForeignKeyRequirement> $requirements Complete supporting key sets for retained foreign-key sides
     */
    public function __construct(
        public readonly TableIdentity $owner,
        array $actions,
        array $requirements,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
        public readonly IndexChangePolicy $policy = new IndexChangePolicy(),
        public readonly bool $ignore = false,
    ) {
        Check::input(!$ignore || $release === GrammarRelease::MySql5651, 'Only MySQL 5.6.51 admits ALTER IGNORE TABLE.');
        Check::input(array_is_list($actions) && $actions !== [], 'Foreign-key removal requires an ordered nonempty action list.');
        $checked = [];
        foreach ($actions as $action) {
            Check::input($action instanceof ForeignKeyRemoval || $action instanceof IndexIdentity, 'Foreign-key removal requires a checked native action.');
            $checked[] = $action;
        }
        (new ForeignKeyRemovalSet())->check($owner, $checked, $requirements, $release, $policy);
        $this->actions = $checked;
    }

    /**
     * Identifies the immutable native DDL profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/drop-foreign-keys';
    }

    /**
     * Emits the resolved qualified owner and every checked clause in native order.
     */
    public function toString(): string
    {
        $verb = $this->ignore ? 'ALTER IGNORE TABLE ' : 'ALTER TABLE ';
        $actions = [];
        foreach ($this->actions as $action) {
            $actions[] = $action instanceof ForeignKeyRemoval ? $action->toString() : 'DROP INDEX ' . $action->name->quoted();
        }

        return $verb . $this->owner->quoted() . ' ' . implode(', ', $actions) . $this->policy->sql(true);
    }
}
