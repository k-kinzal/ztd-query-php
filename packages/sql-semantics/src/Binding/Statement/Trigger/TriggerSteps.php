<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Trigger;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\StatementBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Statement\Insert;
use SqlSemantics\Model\Statement\Mutation;
use SqlSemantics\Model\Trigger\SqliteBody;

/**
 * Classifies each SQLite trigger action with the same native statement forms as direct SQL.
 * @visibility SqlSemantics
 */
final class TriggerSteps
{
    /**
     * @throws UnclassifiedSql
     */
    public static function bind(Node $source, QueryContext $context, Scope $scope): SqliteBody
    {
        $steps = [];
        foreach (Tree::outer($source, ['trigger_cmd']) as $step) {
            $steps[] = self::step($step, $context, $scope);
        }
        if ($steps === []) {
            throw new UnclassifiedSql('A trigger requires at least one classified step.');
        }
        return new SqliteBody($steps);
    }

    /**
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\InvalidSql
     */
    public static function step(Node $source, QueryContext $context, Scope $scope): BoundQuery|Insert\InsertValuesStatement|Insert\InsertSelectStatement|Mutation\UpdateTableStatement|Mutation\UpdateFromStatement|Mutation\DeleteTableStatement
    {
        $step = (new StatementBinder($context->tables))->node($source, $source, $context, $scope);
        $target = Tree::child($source, ['trnm']);
        if ($target !== null && in_array('.', array_column($target->tokens(), 'text'), true)) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::TriggerQualifiedTarget, $target);
        }
        $index = Tree::child($source, ['tridxby']);
        if ($index !== null) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::TriggerIndexHint, $index);
        }
        if ($step instanceof BoundQuery || $step instanceof Insert\InsertValuesStatement || $step instanceof Insert\InsertSelectStatement || $step instanceof Mutation\UpdateTableStatement || $step instanceof Mutation\UpdateFromStatement || $step instanceof Mutation\DeleteTableStatement) {
            return $step;
        }
        throw new UnclassifiedSql('Unclassified trigger action: ' . $source->toString());
    }
}
