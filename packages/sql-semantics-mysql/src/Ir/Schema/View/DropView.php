<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A native removal of named persistent views.
 *
 * @visibility public
 * @example Reading the identity of a view to remove
 *     $identity = new \SqlSemantics\Platform\MySql\Ir\Schema\View\ViewName(new \SqlSemantics\Platform\MySql\Ir\Identifier('app'), new \SqlSemantics\Platform\MySql\Ir\Identifier('accounts'));
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\View\DropView([$identity]))->targets[0]->view->value // => 'accounts'
 */
final class DropView implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<ViewName> Targets in statement order
     */
    public readonly array $targets;

    /**
     * Closes a view removal over an ordered list of distinct identities.
     *
     * @param list<ViewName> $targets
     */
    public function __construct(
        array $targets,
        public readonly bool $ifExists = false,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
    ) {
        $names = Check::listOf($targets, ViewName::class, 'DROP VIEW needs a nonempty target list.', 1);
        $seen = [];
        foreach ($names as $target) {
            $key = $target->quoted();
            Check::input(!isset($seen[$key]), 'DROP VIEW cannot repeat a target.');
            $seen[$key] = true;
        }
        $this->targets = $names;
    }

    /**
     * Identifies the fixed native release and lexical settings.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native';
    }

    /**
     * Emits the removal and its explicit target and behavior.
     */
    public function toString(): string
    {
        $names = [];
        foreach ($this->targets as $target) {
            $names[] = $target->quoted();
        }

        return 'DROP VIEW ' . ($this->ifExists ? 'IF EXISTS ' : '')
            . implode(', ', $names);
    }
}
