<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Preparation;

use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Scope;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use WeakMap;

/**
 * Retains modern prepared WHERE constants by semantic occurrence identity.
 * Preparation owns their warnings; subsequent executions read their values.
 * Row-dependent predicates and runtime parameters remain unevaluated here.
 * Verified through repeated native prepared executions on MySQL 5.7 and 8.4.
 *
 * @visibility MySqlMemory
 */
final class Conditions
{
    /**
     * @param list<Node> $nodes The reached semantic nodes of the prepared query
     */
    public function check(Compiler $compiler, array $nodes): void
    {
        $session = $compiler->connection->session();
        if ($session === null || $compiler->settings->legacy()) {
            return;
        }
        $session->preparation->conditions ??= new WeakMap();
        foreach ($nodes as $node) {
            if ($node instanceof Scalar) {
                unset($session->preparation->conditions[$node]);
            }
        }
        foreach ($nodes as $node) {
            if ($node instanceof Select && $node->where !== null) {
                $this->retain($compiler, $node->where, $session->preparation->conditions);
            }
        }
    }

    /**
     * Evaluates complete constant predicates before splitting varying conjunctions.
     * Whole-expression evaluation preserves constant short-circuit order.
     *
     * @param WeakMap<Scalar, Constant> $values The values owned by this preparation
     */
    public function retain(Compiler $compiler, Scalar $node, WeakMap $values): void
    {
        if (isset($values[$node])) {
            return;
        }
        if ($compiler->constancy($node) === Constancy::Resolved && (new Walker())->find($node, Query::class) === []) {
            $compiled = $compiler->compile($node, new Scope());
            $context = $compiler->connection->context;
            $value = \MySqlMemory\Evaluation\Convert::toBool($compiled->evaluate(new Frame($context)), $compiled->domain(), $context);
            $values[$node] = new Constant($compiler->operators->truth($value === null), $value === null ? null : (int) $value);
        } elseif ($node instanceof Logical) {
            $this->retain($compiler, $node->left, $values);
            $this->retain($compiler, $node->right, $values);
        }
    }
}
