<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem\Location;

use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrame;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Type\Invalid;

/**
 * Leaves deferred frame operands to their frame's preparation phase.
 *
 * Bound semantic facts remain intact. Missing relations still open before
 * ordinary clauses; column, query and operand conditions follow frame shape
 * and ordering-type checks. The frame consumer prepares these exact operands.
 *
 * @visibility MySqlMemory
 */
final class FrameOffsets
{
    /**
     * @return array<int, true> Identities of nodes belonging to deferred frame offsets
     */
    public function nodes(Node $statement): array
    {
        $nodes = [];
        $walker = new Walker();
        foreach ($walker->find($statement, DeferredFrame::class) as $frame) {
            foreach ($frame->offsets() as $offset) {
                foreach ($walker->find($offset, Node::class) as $node) {
                    $nodes[spl_object_id($node)] = true;
                }
            }
        }
        return $nodes;
    }

    /**
     * @return array<int, true> Conditions that the retained frame will check
     */
    public function conditions(Node $statement, Facts $facts): array
    {
        $owned = $this->nodes($statement);
        if ($owned === []) {
            return [];
        }
        $conditions = [];
        foreach ((new Walker())->find($statement, Node::class) as $node) {
            if (!isset($owned[spl_object_id($node)]) || !$facts->covers($node)) {
                continue;
            }
            $problems = $node instanceof Query ? $facts->query($node)->diagnostics : [];
            if ($node instanceof Operand) {
                $fact = $facts->scalar($node);
                $problems = [$fact->resolution, $fact->type instanceof Invalid ? $fact->type->cause : null];
            }
            foreach ($problems as $problem) {
                if ($problem instanceof Diagnostic && !$problem instanceof MissingTable) {
                    $conditions[spl_object_id($problem)] = true;
                }
            }
        }
        return $conditions;
    }
}
