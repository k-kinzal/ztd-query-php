<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization;

use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Parts;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\MergeStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Decision;
use SqlSemantics\Model\Write\MergeAction;

/**

 * Writes ordered MERGE alternatives and their mandatory action payloads. @visibility SqlSemantics

 */
final class Merges
{
    public static function write(MergeStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        $merge = $statement->merge;
        return new Tree('merge', [Query\QueryParts::with($statement->ctes, $dialect), Build::keyword('MERGE INTO'), Query\Relations::write($merge->target, $dialect), Build::keyword('USING'), Query\Relations::write($merge->input, $dialect), Build::keyword('ON'), Expressions::write($merge->condition), ...array_map(self::action(...), $merge->actions), ...($statement->outputs === [] ? [] : [Build::keyword('RETURNING'), Parts::outputs($statement->outputs, $dialect)])]);
    }

    /**
     * @throws InvalidStructure
     */
    public static function action(MergeAction $action): Tree
    {
        $match = match ($action->match) {
            Decision\MatchKind::Matched => 'MATCHED', Decision\MatchKind::MissingSource => 'NOT MATCHED BY SOURCE', Decision\MatchKind::MissingTarget => 'NOT MATCHED',
        };
        $effect = match (true) {
            $action instanceof Decision\MergeNothing => Build::keyword('DO NOTHING'),
            $action instanceof Decision\MergeDelete => Build::keyword('DELETE'),
            $action instanceof Decision\MergeUpdate => new Tree('update', [Build::keyword('UPDATE SET'), Write\Assignments::write($action->assignments)]),
            $action instanceof Decision\MergeInsertDefaults => Build::keyword('INSERT DEFAULT VALUES'),
            $action instanceof Decision\MergeRowInsertion => new Tree('insert', [Build::keyword('INSERT'), ...($action->insertion->explicitColumns ? [Build::parentheses(Build::separated(array_map(Write\StoragePaths::write(...), $action->insertion->columns)))] : []), Parts::rows([$action->row->items])]),
            default => throw new InvalidStructure('Unclassified MERGE action.'),
        };
        return new Tree('merge-action', [Build::keyword('WHEN ' . $match), ...($action->condition === null ? [] : [Build::keyword('AND'), Expressions::write($action->condition)]), Build::keyword('THEN'), $effect]);
    }
}
