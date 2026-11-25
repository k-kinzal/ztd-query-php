<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

use SqlSemantics\Platform\MySql\Ir\Schema\Index\Batch\IndexRemovalSet;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * One atomic removal of distinct physical keys of a resolved MySQL table.
 * @visibility public
 * @example Dropping two known secondary indexes
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $catalog = $semantics->context(['CREATE TABLE app.t (a INT, b INT, KEY by_a(a), KEY by_b(b))']);
 *     count($semantics->analyze('ALTER TABLE app.t DROP KEY by_a, DROP KEY by_b', $catalog)->targets) // => 2
 */
final class DropIndexes implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<IndexIdentity> Physical keys in the selected removal order
     */
    public readonly array $targets;

    /**
     * Closes every ordered removal and their shared owner and policy.
     * @param list<IndexIdentity> $targets Exact physical targets of the same owner
     */
    public function __construct(
        array $targets,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
        public readonly IndexChangePolicy $policy = new IndexChangePolicy(),
        public readonly bool $ignore = false,
    ) {
        Check::input(!$ignore || $release === GrammarRelease::MySql5651, 'Only MySQL 5.6.51 admits ALTER IGNORE TABLE.');
        $checked = Check::listOf($targets, IndexIdentity::class, 'An atomic index removal requires an ordered list of at least two targets.', 2);
        Check::input(count($checked) <= 64, 'An atomic index removal can contain at most 64 physical keys.');
        (new IndexRemovalSet())->check($checked, $policy);
        $this->targets = $checked;
    }

    /**
     * Names the stock database and immutable grammar profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/drop-index-set';
    }

    /**
     * Emits one native statement with the complete ordered action list.
     */
    public function toString(): string
    {
        $operation = $this->ignore ? 'ALTER IGNORE TABLE ' : 'ALTER TABLE ';
        $actions = [];
        foreach ($this->targets as $index) {
            $actions[] = 'DROP INDEX ' . $index->name->quoted();
        }

        return $operation . $this->targets[0]->owner->quoted() . ' ' . implode(', ', $actions) . $this->policy->sql(true);
    }
}
