<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Recursion;

use SqlSemantics\Platform\MySql\Rules\Query\CommonTables;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Statement\Query as M;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Node;

/**
 * Fixed recursive-reference roles, separate from table contents and recursion limits.
 *
 * Definitions retain unused templates; only a read that reaches the definition
 * requires a coherent anchor and direct, single recursive references per block.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Constraints
{
    /**
     * Finds a lexical reference-position error even inside an inactive instruction.
     * Other recursive constraints are deferred until that instruction prepares.
     */
    public function position(Node $body): ?M\Problem\Misuse
    {
        foreach ($this->definitions($body) as $table) {
            $problem = $this->definition($table);
            if ($problem?->rule === M\Problem\MisuseRule::RecursiveReferencePosition) {
                return $problem;
            }
        }
        return null;
    }

    /**
     * Finds a contradiction among the recursive definitions this read reaches.
     */
    public function problem(Node $query): ?M\Problem\Misuse
    {
        foreach ($this->definitions($query) as $table) {
            $problem = $this->definition($table);
            if ($problem !== null) {
                return $problem;
            }
        }
        return null;
    }

    /**
     * @return list<M\With\CommonTableExpression> Reached definitions with recursive name visibility
     */
    public function definitions(Node $query): array
    {
        $nodes = (new ReadDependencies())->nodes($query);
        $reached = array_fill_keys(array_map(spl_object_id(...), $nodes), true);
        $definitions = [];
        foreach ($nodes as $node) {
            if (!$node instanceof M\QueryExpression || !$node->with instanceof M\With\With || !$node->with->recursive) {
                continue;
            }
            foreach ($node->with->tables as $table) {
                if (isset($reached[spl_object_id($table)])) {
                    $definitions[] = $table;
                }
            }
        }
        return $definitions;
    }

    /**
     * Checks one reached definition in its own lexical scope.
     */
    public function definition(M\With\CommonTableExpression $table, Comparison $names = Comparison::Sensitive): ?M\Problem\Misuse
    {
        $references = (new References())->of($table->query, $table->name, $names);
        if ($references === []) {
            return null;
        }
        $blocks = [];
        foreach ($references as [$block, $depth]) {
            if ($depth > 0 || isset($blocks[$block])) {
                return new M\Problem\Misuse(M\Problem\MisuseRule::RecursiveReferencePosition, $table->name);
            }
            $blocks[$block] = true;
        }
        $operands = (new CommonTables())->operands($table->query);
        if (count($operands) < 2) {
            return new M\Problem\Misuse(M\Problem\MisuseRule::RecursiveWithoutUnion, $table->name);
        }
        return (new References())->of($operands[0], $table->name, $names) === [] ? null : new M\Problem\Misuse(M\Problem\MisuseRule::RecursiveWithoutAnchor, $table->name);
    }
}
