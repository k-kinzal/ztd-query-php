<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * An atomic rename of one or more indexes of one resolved MySQL table.
 * @visibility public
 * @example Swapping two physical index names
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $catalog = $semantics->context(['CREATE TABLE app.t (a INT, b INT, KEY left_key(a), KEY right_key(b))']);
 *     count($semantics->analyze('ALTER TABLE app.t RENAME KEY left_key TO right_key, RENAME KEY right_key TO left_key', $catalog)->moves) // => 2
 */
final class RenameIndexes implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<IndexRename> Ordered moves in the atomic statement
     */
    public readonly array $moves;

    /**
     * Checks the initial and resulting native namespaces and the checked policy.
     * @param list<IndexRename> $moves Relative names in the selected owner's namespace
     * @param list<Identifier> $unmoved Other physical names in the complete namespace
     */
    public function __construct(
        public readonly TableIdentity $owner,
        array $moves,
        array $unmoved,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
        public readonly IndexChangePolicy $policy = new IndexChangePolicy(),
    ) {
        Check::input($release !== GrammarRelease::MySql5651, 'Native index renaming requires MySQL 5.7 or later.');
        Check::input($policy->supports($owner->kind), 'The algorithm and lock cannot rename indexes on this native InnoDB table.');
        $checked = Check::listOf($moves, IndexRename::class, 'An atomic index rename requires a nonempty ordered move list.', 1);
        $stable = Check::listOf($unmoved, Identifier::class, 'An atomic index rename requires an ordered list of unmoved names.');
        (new IndexRenameNamespace())->check($checked, $stable);
        $this->moves = $checked;
    }

    /**
     * Identifies the immutable native grammar and session profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/rename-indexes';
    }

    /**
     * Emits the moves as one ALTER TABLE against the original namespace.
     */
    public function toString(): string
    {
        $actions = [];
        foreach ($this->moves as $move) {
            $actions[] = $move->toString();
        }

        return 'ALTER TABLE ' . $this->owner->quoted() . ' ' . implode(', ', $actions) . $this->policy->sql(true);
    }
}
