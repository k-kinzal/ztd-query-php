<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
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
     */
    public function __construct(
        public readonly IndexIdentity $target,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
    ) {
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
        return 'DROP INDEX ' . $this->target->name->quoted() . ' ON ' . $this->target->owner->quoted();
    }
}
