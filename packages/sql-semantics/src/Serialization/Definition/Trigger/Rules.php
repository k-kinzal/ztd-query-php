<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Trigger;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Relation\Rule\RuleEvent;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\Rule as Statement;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Query\Relations;
use SqlSemantics\Serialization\Statements;

/**
 * Writes rewrite rules with their actions as native statements.
 * @visibility SqlSemantics
 */
final class Rules
{
    /**
     * Returns null for other statements; several actions are parenthesized and separated by semicolons.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if ($statement instanceof Statement\CreateEmptyRuleStatement) {
            return new Tree('create-rule', [...self::head($statement->orReplace, $statement->name, $statement->event, $statement->table, $statement->condition, $statement->instead), Build::keyword('NOTHING')]);
        }
        if (!$statement instanceof Statement\CreateCommandRuleStatement) {
            return null;
        }
        $actions = [];
        foreach ($statement->actions as $action) {
            if ($actions !== []) {
                $actions[] = new Atom('punctuation', ';');
            }
            $actions[] = Statements::write($action);
        }
        $body = count($statement->actions) === 1 ? $actions[0] : new Tree('rule-actions', [new Atom('punctuation', '('), ...$actions, new Atom('punctuation', ')')]);
        return new Tree('create-rule', [...self::head($statement->orReplace, $statement->name, $statement->event, $statement->table, $statement->condition, $statement->instead), $body]);
    }

    /**
     * Everything before the actions, with ALSO written explicitly.
     * @return list<Tree>
     */
    public static function head(bool $orReplace, string $name, RuleEvent $event, TableReference $table, ?Expression $condition, bool $instead): array
    {
        return [
            Build::keyword($orReplace ? 'CREATE OR REPLACE RULE' : 'CREATE RULE'),
            Build::identifier([$name], Dialect::PostgreSql),
            Build::keyword('AS ON ' . $event->value . ' TO'),
            Relations::target($table, Dialect::PostgreSql),
            ...($condition === null ? [] : [Build::keyword('WHERE'), Expressions::write($condition)]),
            Build::keyword($instead ? 'DO INSTEAD' : 'DO ALSO'),
        ];
    }
}
