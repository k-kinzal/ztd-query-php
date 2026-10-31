<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Set;

use SqlParser\Parser\Node;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignment;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredAssignments;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;

/**
 * Reads complete stored assignment lists with ordinary or deferred value roles.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class StoredAssignments
{
    /**
     * @param Lowering $lowering The enclosing language interpretation
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Returns a complete assignment statement, leaving transaction and password forms to their rules.
     */
    public function read(Node $node): SetVariables|DeferredAssignments|null
    {
        while (count($node->children) === 1 && $node->children[0] instanceof Node) {
            $node = $node->children[0];
        }
        if ($node->name !== 'set') {
            return null;
        }
        $list = $this->lowering->form($this->lowering->form($node)->node(1));
        $scope = null;
        if ($list->signature === 'start_option_value_list: option_type start_option_value_list_following_option_type') {
            $scope = (new SetRule($this->lowering))->scope($list->node(0));
            $list = $this->lowering->form($list->node(1));
        }
        if (!in_array($list->signature, ['start_option_value_list: option_value_no_option_type option_value_list_continued', 'start_option_value_list_following_option_type: option_value_following_option_type option_value_list_continued'], true)) {
            return null;
        }
        $first = $this->lowering->form($list->node(0));
        if ((new ItemRule($this->lowering))->password($first)) {
            return null;
        }
        $items = [(new StoredItems($this->lowering))->read($first, $scope), ...$this->continued($list->node(1))];
        if (array_filter($items, static fn ($item): bool => $item instanceof DeferredAssignment) !== []) {
            return new DeferredAssignments($items);
        }
        $ordinary = [];
        foreach ($items as $item) {
            \SqlSemantics\Diagnostic\Check::invariant(!$item instanceof DeferredAssignment, 'An ordinary SET contains only scalar-valued items.');
            $ordinary[] = $item;
        }
        return new SetVariables($ordinary);
    }

    /**
     * Reads every item after the first with its explicitly written scope.
     *
     * @return list<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem|DeferredAssignment>
     */
    public function continued(Node $continued): array
    {
        if ($continued->children === []) {
            return [];
        }
        $items = [];
        $form = $this->lowering->form($continued);
        foreach ((new Lists())->items($form->node(1)) as $node) {
            $item = $this->lowering->form($node);
            $scoped = $item->signature === 'option_value: option_type option_value_following_option_type';
            $scope = $scoped ? (new SetRule($this->lowering))->scope($item->node(0)) : null;
            $items[] = (new StoredItems($this->lowering))->read($this->lowering->form($item->node($scoped ? 1 : 0)), $scope);
        }
        return $items;
    }
}
