<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;

/**
 * Retains typed routine headers before an intrinsic body operand refusal.
 *
 * Header validation still precedes the body's diagnostic. Parser nodes are
 * consumed here and are never retained in the refusal.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RefusedBodyHeader
{
    /**
     * Attaches the enclosing routine's declared types without constructing its invalid body.
     */
    public function wrap(Node $tree, RejectedInput $failure, Lowering $lowering): RejectedInput
    {
        $ranges = new SourceRanges();
        $tails = [...$tree->find('sp_tail'), ...$tree->find('sf_tail'), ...$tree->find('trigger_tail'), ...$tree->find('event_tail')];
        usort($tails, static fn (Node $left, Node $right): int => ($ranges->span($left)[0] ?? 0) <=> ($ranges->span($right)[0] ?? 0));
        foreach ($tails as $tail) {
            $range = $ranges->span($tail);
            if ($range !== null && $range[0] < $failure->end && $range[1] >= $failure->end) {
                if (!in_array($tail->name, ['sp_tail', 'sf_tail'], true)) {
                    return $failure;
                }
                $definers = array_values(array_filter($tree->find('definer'), static fn (Node $node): bool => ($ranges->span($node)[1] ?? PHP_INT_MAX) <= $range[0]));
                $definer = $definers === [] ? null : $lowering->users->definer($definers[0]);
                return $this->routine($tail, $failure, $lowering, $definer);
            }
        }
        return $failure;
    }

    /**
     * Reads parameter and return declarations in header order.
     */
    public function routine(Node $tail, RejectedInput $failure, Lowering $lowering, ?Account $definer): RejectedInput
    {
        $parts = [];
        foreach ($tail->children as $child) {
            if ($child instanceof Node) {
                $parts[$child->name] = $child;
            }
        }
        $parameters = new ParameterRule($lowering);
        $function = $tail->name === 'sf_tail';
        $parameterTree = $parts[$function ? 'sp_fdparam_list' : 'sp_pdparam_list'];
        $returnTree = $function ? ($parts['type_with_opt_collate'] ?? $parts['type']) : null;
        [$returns, $collation] = $returnTree === null ? [null, null] : $parameters->declared($returnTree, $parts['opt_collate'] ?? null);
        \SqlSemantics\Diagnostic\Check::invariant($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem, 'A refused body carries a program diagnostic.');
        $problem = new RefusedProgramBody($lowering->names->qualified($parts['sp_name']), $function ? ProgramKind::Function : ProgramKind::Procedure, $failure->problem, $definer, $parameters->parameters($parameterTree), $returns, $collation, (new DefinitionBody())->typeEnds($parameterTree, $returnTree), (new SourceRanges())->span($parts['sp_name'])[1] ?? 0);

        return new RejectedInput($problem, $failure->end, $failure->aborts);
    }
}
