<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexDependencyClosure;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexForeignKeyRequirement;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Removal of a resolved ordinary MySQL InnoDB index.
 *
 * @visibility public
 * @example Dropping the declared unique key on the indexed table
 *     $analyzer = new \SqlSemantics\Platform\MySql\Semantics();
 *     $catalog = $analyzer->context(['CREATE TABLE app.accounts (id INT, UNIQUE INDEX by_id(id)) ENGINE=InnoDB']);
 *     $analyzer->analyze('DROP INDEX BY_ID ON app.accounts', $catalog)->toString() // => 'DROP INDEX `by_id` ON `app`.`accounts`'
 */
final class DropIndex implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the resolved removal target and the native release.
     * @param list<IndexForeignKeyRequirement> $requirements Complete native foreign-key sides owning supporting physical keys
     */
    public function __construct(
        public readonly IndexIdentity $target,
        array $requirements,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
        public readonly IndexChangePolicy $policy = new IndexChangePolicy(),
        public readonly bool $ignore = false,
    ) {
        Check::input(
            $target->autoIncrement === null || $target->autoIncrement->alternates !== [],
            'DROP INDEX must retain a backing key for the AUTO_INCREMENT field.'
        );
        Check::input(!$ignore || $release === GrammarRelease::MySql5651, 'Only MySQL 5.6.51 admits ALTER IGNORE TABLE.');
        Check::input(
            $policy->supports($target->owner->kind, $target->role->clustered()),
            'The algorithm and lock cannot remove this native InnoDB index.'
        );
        (new IndexDependencyClosure())->check([$target], $requirements);
    }

    /**
     * Identifies the native dialect profile under which the index is removed.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/drop-index';
    }

    /**
     * Emits one absolute table and its resolved physical index name.
     */
    public function toString(): string
    {
        $name = $this->target->name->quoted();
        $owner = $this->target->owner->quoted();
        $policy = $this->policy->sql($this->ignore);

        return $this->ignore
            ? 'ALTER IGNORE TABLE ' . $owner . ' DROP INDEX ' . $name . $policy
            : 'DROP INDEX ' . $name . ' ON ' . $owner . $policy;
    }
}
