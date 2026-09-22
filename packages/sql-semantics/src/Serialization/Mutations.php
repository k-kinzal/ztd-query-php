<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization;

use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Parts;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\DeleteStatement;
use SqlSemantics\Model\Statement\Mutation;
use SqlSemantics\Model\Statement\UpdateStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Policy\ConstraintResponse;

/**
 * Writes required table inputs from native single-table, FROM and joined mutation forms.
 *
 * @visibility SqlSemantics
 */
final class Mutations
{
    /**
     * Writes predicates and results independently of the operation's input form.
     */
    public static function write(UpdateStatement|DeleteStatement $statement, bool $trigger = false): Tree
    {
        $dialect = $statement->origin->dialect;
        $head = $statement instanceof UpdateStatement ? self::update($statement, $trigger) : self::delete($statement, $trigger);
        $tail = $statement instanceof Mutation\UpdateTableStatement || $statement instanceof Mutation\DeleteTableStatement
            ? [Parts::ordering($statement->orderBy), Query\QueryParts::pagination($statement->limit, null, false, $dialect)] : [];
        return new Tree('mutation', [Query\QueryParts::with($statement->ctes, $dialect), $head, Parts::expressions('WHERE', $statement->where === null ? [] : [$statement->where]), ...$tail, ...($statement->outputs === [] ? [] : [Build::keyword('RETURNING'), Parts::outputs($statement->outputs, $dialect)])]);
    }

    /**
     * Keeps an UPDATE FROM source distinct from its writable table.
     * @throws InvalidStructure
     */
    public static function update(UpdateStatement $statement, bool $trigger = false): Tree
    {
        $dialect = $statement->origin->dialect;
        $input = match (true) {
            $statement instanceof Mutation\UpdateTableStatement,
            $statement instanceof Mutation\UpdateFromStatement => $trigger ? Build::identifier([$statement->target->declaration->name], $dialect) : Query\Relations::write($statement->target, $dialect),
            $statement instanceof Mutation\UpdateJoinedStatement => Query\Relations::write($statement->from, $dialect),
            default => throw new InvalidStructure('Unclassified UPDATE form.'),
        };
        $modifiers = [];
        if (($statement instanceof Mutation\UpdateTableStatement || $statement instanceof Mutation\UpdateFromStatement) && $statement->onViolation !== ConstraintResponse::Default) {
            $modifiers[] = Build::keyword('OR ' . $statement->onViolation->value);
        }
        if ($statement instanceof Mutation\UpdateTableStatement || $statement instanceof Mutation\UpdateJoinedStatement) {
            if ($statement->lowPriority) {
                $modifiers[] = Build::keyword('LOW_PRIORITY');
            }
            if ($statement->ignore) {
                $modifiers[] = Build::keyword('IGNORE');
            }
        }
        return new Tree('update', [Build::keyword('UPDATE'), ...$modifiers, $input, Build::keyword('SET'), Write\Assignments::write($statement->writes), ...($statement instanceof Mutation\UpdateFromStatement ? [Build::keyword('FROM'), Query\Relations::write($statement->from, $dialect)] : [])]);
    }

    /**
     * Writes deletion targets separately from additional read relations.
     * @throws InvalidStructure
     */
    public static function delete(DeleteStatement $statement, bool $trigger = false): Tree
    {
        $dialect = $statement->origin->dialect;
        $modifiers = [];
        if ($statement instanceof Mutation\DeleteTableStatement || $statement instanceof Mutation\DeleteJoinedStatement) {
            foreach (['LOW_PRIORITY' => $statement->lowPriority, 'QUICK' => $statement->quick, 'IGNORE' => $statement->ignore] as $keyword => $enabled) {
                if ($enabled) {
                    $modifiers[] = Build::keyword($keyword);
                }
            }
        }
        $parts = match (true) {
            $statement instanceof Mutation\DeleteTableStatement => [Build::keyword('FROM'), $trigger ? Build::identifier([$statement->target->declaration->name], $dialect) : Query\Relations::write($statement->target, $dialect)],
            $statement instanceof Mutation\DeleteUsingStatement => [Build::keyword('FROM'), $trigger ? Build::identifier([$statement->target->declaration->name], $dialect) : Query\Relations::write($statement->target, $dialect), Build::keyword('USING'), Query\Relations::write($statement->using, $dialect)],
            $statement instanceof Mutation\DeleteJoinedStatement => [Build::separated(array_map(static fn ($table): Tree => Build::identifier([$table->alias ?? $table->declaration->name], $dialect), $statement->targets)), Build::keyword('FROM'), Query\Relations::write($statement->from, $dialect)],
            default => throw new InvalidStructure('Unclassified DELETE form.'),
        };
        return new Tree('delete', [Build::keyword('DELETE'), ...$modifiers, ...$parts]);
    }
}
