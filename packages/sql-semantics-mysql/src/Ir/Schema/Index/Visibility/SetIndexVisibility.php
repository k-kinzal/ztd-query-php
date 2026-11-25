<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A native visibility change on the secondary physical indexes of one resolved InnoDB table.
 * @visibility public
 * @example Hiding an existing optimizer index
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $catalog = $semantics->context(['CREATE TABLE app.t (a INT, KEY By_A(a))']);
 *     $semantics->analyze('ALTER TABLE app.t ALTER INDEX by_a INVISIBLE', $catalog)->toString() // => 'ALTER TABLE `app`.`t` ALTER INDEX `By_A` INVISIBLE'
 */
final class SetIndexVisibility implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<IndexVisibilityEdit> Visibility assignments in native execution order
     */
    public readonly array $edits;

    /**
     * Closes all native visibility assignments against one table and its effective policy.
     * @param list<IndexVisibilityEdit> $edits Checked relative indexes of the resolved owner
     */
    public function __construct(
        public readonly TableIdentity $owner,
        array $edits,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
        public readonly IndexChangePolicy $policy = new IndexChangePolicy(),
    ) {
        $available = match ($release) {
            GrammarRelease::MySql5651, GrammarRelease::MySql5744 => false,
            GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830,
            GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910 => true,
        };
        Check::input($available, 'Native index visibility requires MySQL 8.0 or later.');
        $checked = Check::listOf($edits, IndexVisibilityEdit::class, 'Index visibility requires an ordered nonempty edit list.', 1);
        Check::input($policy->supports($owner->kind), 'The algorithm and lock cannot change native index visibility on this table.');
        foreach ($checked as $edit) {
            Check::input(
                $owner->kind !== ExternalRelationKind::TemporaryTable || $edit->kind !== SecondaryIndexKind::FullText,
                'An InnoDB temporary table cannot own a FULLTEXT index.'
            );
        }
        $this->edits = $checked;
    }

    /**
     * Identifies the fixed native relation and optimizer settings.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/index-visibility';
    }

    /**
     * Emits each ordered operation and the effective native algorithm and lock.
     */
    public function toString(): string
    {
        $clauses = [];
        foreach ($this->edits as $edit) {
            $clauses[] = $edit->toString();
        }

        return 'ALTER TABLE ' . $this->owner->quoted() . ' ' . implode(', ', $clauses) . $this->policy->sql(true);
    }
}
