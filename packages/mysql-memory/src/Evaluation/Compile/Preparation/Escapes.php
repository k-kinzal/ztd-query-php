<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Preparation;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Compiler;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\StatementSemantics;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

/**
 * Preserves legacy prepared ESCAPE correlation checks before scalar merging.
 *
 * DEFAULT(column) is constant after resolution, but MySQL 5.6 and 5.7 refuse
 * a prepared escape subquery that reaches an outer column through DEFAULT.
 * Text execution accepts the merged default. Verified through both protocols.
 *
 * @visibility MySqlMemory
 */
final class Escapes
{
    /**
     * Checks the reached LIKE operands without evaluating a query or reading SQL.
     *
     * @param list<Node> $nodes The reached semantic nodes
     * @throws \MySqlMemory\Error\SqlError When a legacy prepared escape is correlated
     */
    public function check(Compiler $compiler, array $nodes): void
    {
        if (!$compiler->settings->legacy()) {
            return;
        }
        foreach ($nodes as $node) {
            if ($node instanceof Like && $node->escape !== null && $this->correlated($node->escape, $compiler->facts)) {
                throw StatementError::WrongArguments->error('ESCAPE');
            }
        }
    }

    /**
     * Finds a default reference crossing the escape's query boundary.
     */
    public function correlated(Node $node, StatementSemantics $facts, int $depth = 0): bool
    {
        if ($node instanceof DefaultOfColumn && $depth > 0 && $node->column->hasBinding()) {
            $resolution = $node->column->scalarInformation()->resolution;
            return $resolution instanceof ResolvedColumn && $resolution->depth >= $depth;
        }
        $children = [];
        $properties = get_object_vars($node);
        array_walk_recursive($properties, static function ($value) use (&$children): void {
            if ($value instanceof Node) {
                $children[] = $value;
            }
        });
        foreach ($children as $child) {
            if ($this->correlated($child, $facts, $depth + ($node instanceof Query ? 1 : 0))) {
                return true;
            }
        }
        return false;
    }
}
