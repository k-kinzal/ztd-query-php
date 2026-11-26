<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Batch\IndexRemovalSet;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexDependencyClosure;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexForeignKeyRequirement;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;

/**
 * Closes a native constraint removal and optional index removals over one owner.
 * @visibility SqlSemantics
 */
final class ForeignKeyRemovalSet
{
    /**
     * Checks native placement, duplicates, and retained key support before publication.
     * @param non-empty-list<ForeignKeyRemoval|IndexRemovalTarget> $actions Written native key alterations
     * @param list<IndexForeignKeyRequirement> $requirements Physical support for every remaining foreign-key side
     */
    public function check(TableIdentity $owner, array $actions, array $requirements, GrammarRelease $release, IndexChangePolicy $policy): void
    {
        $temporary = $owner->kind === ExternalRelationKind::TemporaryTable;
        $legacy = $this->legacy($release);
        $expected = $temporary ? ForeignKeyRemovalKind::IgnoredOnTemporary : ForeignKeyRemovalKind::Constraint;
        $physical = new IndexRemovalSet();
        $coordinate = $physical->coordinate($owner);
        $case = new NativeIdentifierCase();
        $keys = $indexes = [];
        foreach ($actions as $action) {
            if ($action instanceof ForeignKeyRemoval) {
                Check::input($action->kind === $expected && (!$temporary || $legacy), 'The foreign-key action does not belong to this native table lifetime.');
                $name = $action->name === null ? '' : $case->fold($action->name->value);
                Check::input($legacy || !isset($keys[$name]), 'This MySQL release cannot repeat a foreign-key removal.');
                $keys[$name] = true;
            } else {
                Check::input(
                    $physical->coordinate($action->index->owner) === $coordinate,
                    'A mixed foreign-key and index removal requires one owning table and lifetime.'
                );
                $indexes[] = $action;
            }
        }
        Check::input($keys !== [], 'The native action group needs at least one foreign-key removal.');
        if ($indexes === []) {
            Check::input($requirements === [], 'A foreign-key-only removal does not consume index requirements.');
            Check::input($policy->supports($owner->kind), 'The algorithm and lock cannot remove native foreign keys on this table.');
        } else {
            $identities = $physical->check($indexes, $policy);
            (new IndexDependencyClosure())->check($identities, $requirements);
        }
    }

    /**
     * Identifies the releases that accept repeated and ignored temporary actions.
     */
    public function legacy(GrammarRelease $release): bool
    {
        return match ($release) {
            GrammarRelease::MySql5651, GrammarRelease::MySql5744 => true,
            GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830,
            GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910 => false,
        };
    }
}
