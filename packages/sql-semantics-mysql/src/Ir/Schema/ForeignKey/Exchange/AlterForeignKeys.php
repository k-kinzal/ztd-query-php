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
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Contract\InnoDbKeyLimit;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\ForeignKeyIndexSide;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * One native ALTER TABLE that adds resolved foreign keys alongside other key edits.
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
     * @var non-empty-list<ForeignKeyAddition|ForeignKeyRemoval|IndexAddition|IndexIdentity> Checked clauses in their native order
     */
    public readonly array $actions;

    /**
     * @var list<Identifier> Prior implicit indexes replaced by the added physical keys
     */
    public readonly array $removedIndexes;

    /**
     * The resolved unique key promoted after an explicit clustered removal.
     * A clustered removal with a null replacement selects the native synthetic row identity.
     */
    public readonly ?IndexIdentity $clusteredReplacement;

    /**
     * Checks all ordered edit roles against the starting schema before publishing the atomic operation.
     *
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $actions The ordered native key edits
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $keys The original physical key catalog
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $constraints The original constraint catalog
     * @param list<ForeignKeyIndexSide>|null $remainingSides Complete retained foreign-key sides affected by explicit physical removals
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
        ?array $remainingSides = null,
    ) {
        [$checked, $additions, $removals, $indexes, $foreignSlots, $physicalRemovals] = (new ForeignKeyExchangeActions())->check($actions);
        $originalKeys = Check::listOf($keys, ForeignKeyNativeKey::class, 'Foreign-key exchange requires checked original physical keys.');
        $originalConstraints = Check::listOf($constraints, ForeignKeyConstraintIdentity::class, 'Foreign-key exchange requires checked original symbols.');
        if ($removals !== []) {
            (new ForeignKeyRemovalSet())->check($owner, $removals, [], $release, $policy);
            (new ForeignKeyExchangeRemovals())->check($owner, $removals, $originalConstraints);
        }
        Check::input(($physicalRemovals === []) === ($remainingSides === null), 'An explicit physical removal requires a complete retained foreign-key layout.');
        $retainedSides = $remainingSides === null ? [] : Check::listOf($remainingSides, ForeignKeyIndexSide::class, 'The retained layout must contain checked foreign-key sides.');
        $support = new ForeignKeyExchangeSupport();
        [$retainedKeys, $removedKeys] = $support->survivors($owner, $originalKeys, $physicalRemovals, $policy);
        $replaced = (new ForeignKeyAdditionSet())->check(
            $owner,
            $additions,
            $originalKeys,
            $originalConstraints,
            $release,
            $policy,
            $ignore,
            $keyLimit,
            $indexes,
            $foreignSlots,
            $physicalRemovals === [] ? null : $retainedKeys
        );
        $replacement = null;
        if ($physicalRemovals !== []) {
            $support->retains($owner, $retainedSides, $originalConstraints, $removals);
            $replacement = $support->check($owner, $retainedKeys, $replaced, $additions, $indexes, $physicalRemovals, $removedKeys, $retainedSides, $release);
        }
        $this->clusteredReplacement = $replacement;
        $this->removedIndexes = $replaced;
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
            if ($action instanceof IndexIdentity) {
                $actions[] = 'DROP INDEX ' . $action->name->quoted();

                continue;
            }
            $actions[] = $action->toString();
        }

        return $verb . $this->owner->quoted() . ' ' . implode(', ', $actions) . $this->policy->sql(true);
    }
}
