<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\DropDependency;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\AbsentDropTarget;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Validates that the native group may remove every selected referenced table.
 * @visibility SqlSemantics
 */
final class DropTableDependencies
{
    /**
     * Enforces the native persistent graph and the release-specific execution order.
     * @param non-empty-list<TableIdentity|AbsentDropTarget> $targets Checked ordered table removals
     * @param list<TableDropReference> $references Complete native incoming edges for those targets
     */
    public function check(array $targets, array $references, GrammarRelease $release): void
    {
        $legacy = match ($release) {
            GrammarRelease::MySql5651, GrammarRelease::MySql5744 => true,
            GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830,
            GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910 => false,
        };
        $positions = [];
        foreach ($targets as $index => $target) {
            if ($target instanceof TableIdentity && $target->kind === ExternalRelationKind::BaseTable) {
                $positions[$target->name->schema->value][$target->name->table->value] = $index;
            }
        }
        foreach ($references as $reference) {
            $parent = $positions[$reference->parent->name->schema->value][$reference->parent->name->table->value] ?? null;
            $child = $positions[$reference->child->name->schema->value][$reference->child->name->table->value] ?? null;
            Check::input($parent !== null, 'A DROP TABLE dependency must name a selected persistent parent.');
            Check::input($child !== null, 'DROP TABLE must include every referencing persistent child.');
            Check::input(!$legacy || $child <= $parent, 'This MySQL release must drop a referencing child before its parent.');
        }
    }
}
