<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\ParameterRule;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramFacts;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Resolves completed condition and declaration operands before a rejected program value.
 *
 * Prefix blocks contain only constructor-accepted operands. Existing program
 * rules resolve condition scopes and declaration conflicts; binding failures
 * that belong to later preparation do not displace the input rejection.
 * Temporary parse nodes and frames never enter a published operation.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class InputPrefix
{
    /**
     * @param Lowering $lowering The input's language interpretation
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Answers an earlier completed program problem, if one precedes the refused operand.
     */
    public function before(Node $tree, int $end): ?RejectedInput
    {
        $frame = new PrefixFrame();
        $parameters = new ParameterList([]);
        $ranges = new SourceRanges();
        $pending = [$tree];
        while ($pending !== []) {
            $node = array_pop($pending);
            if ($node === null) {
                $parent = $frame->parent;
                if ($parent !== null) {
                    $parent->statements[] = new Block($frame->declarations, $frame->statements);
                    $frame = $parent;
                }
                continue;
            }
            $range = $ranges->span($node);
            if ($range === null || $range[0] >= $end) {
                continue;
            }
            $completed = $range[1] < $end;
            if ($completed && in_array($node->name, ['sp_decl', 'sp_proc_stmt', 'ev_sql_stmt_inner', 'sp_pdparam_list', 'sp_fdparam_list'], true)) {
                $parameters = $this->completed($node, $frame, $parameters);
                if ($parameters instanceof RejectedInput) {
                    return $parameters;
                }
                $problem = $this->diagnostic($frame->body(), $parameters) ?? $this->immediate($frame->body(), $tree->find('sf_tail') !== [] ? ProgramKind::Function : ProgramKind::Procedure);
                if ($problem !== null) {
                    return new RejectedInput($problem, $range[1]);
                }
                continue;
            }
            if ($node->name === 'sp_block_content') {
                $frame = new PrefixFrame($frame);
                $pending[] = null;
            }
            foreach (array_reverse($node->children) as $child) {
                if ($child instanceof Node) {
                    $pending[] = $child;
                }
            }
        }

        return null;
    }

    /**
     * Preserves completed instruction restrictions before a later invalid operand.
     * Body-wide return and result-set requirements await the complete declaration.
     */
    public function immediate(Block $body, ProgramKind $kind): ?ProgramProblem
    {
        $problem = (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyStructure())->problem($body, $kind, new QualifiedName(new Name('prefix')));

        return $problem !== null && in_array($problem->rule, [ProgramRule::ReturnOutsideFunction, ProgramRule::BadStatement, ProgramRule::RecursiveCreate, ProgramRule::NestedAlterOrDrop, ProgramRule::EventRecursion], true) ? $problem : null;
    }

    /**
     * Adds a completed declaration or statement, preserving an input-operand refusal.
     *
     * @param bool $isolated Whether enclosing declarations are unavailable to this prefix
     */
    public function completed(Node $node, PrefixFrame $frame, ParameterList $parameters, bool $isolated = false): ParameterList|RejectedInput
    {
        $name = (new \SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxTableNames())->within($node, $this->lowering);
        if ($name !== null) {
            return $name;
        }
        try {
            if (in_array($node->name, ['sp_pdparam_list', 'sp_fdparam_list'], true)) {
                return (new ParameterRule($this->lowering))->parameters($node);
            }
            if ($node->name === 'sp_decl') {
                $frame->declarations[] = (new BlockRule($this->lowering))->declaration($node);
            } else {
                $frame->statements[] = (new StatementRule($this->lowering))->statement($node);
            }
        } catch (RejectedInput $failure) {
            if (!$isolated && $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedSignalItems) {
                $frame->statements[] = $failure->problem->prefix;
                if ($failure->problem->problem instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem) {
                    $frame->statements[] = new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal(null, [$failure->problem->problem]);
                }
                return new RejectedInput($this->diagnostic($frame->body(), $parameters) ?? $failure->problem->diagnostic(), $failure->end);
            }
            return $failure;
        }

        return $parameters;
    }

    /**
     * Resolves prefix scopes using the ordinary rules for declarations and condition names.
     */
    public function diagnostic(Block $body, ParameterList $parameters, bool $scopeComplete = true): ?Diagnostic
    {
        $derivation = new Derivation(new AnalysisContext($this->lowering->profile, [new Name(SessionDatabase::UNNAMED)], complete: false, columnNames: Comparison::AsciiInsensitive));
        (new ProgramFacts())->routine($parameters, $body, ProgramKind::Procedure, new QualifiedName(new Name('prefix')), $derivation, scopeComplete: $scopeComplete);
        foreach ($derivation->facts()->diagnostics as $problem) {
            if ($problem instanceof UnknownSystemVariable || ($problem instanceof ProgramProblem && in_array($problem->rule, [ProgramRule::IncorrectName, ProgramRule::NameTooLong, ProgramRule::DuplicateVariable, ProgramRule::DuplicateCondition, ProgramRule::DuplicateCursor, ProgramRule::DuplicateHandler, ProgramRule::DuplicateParameter, ProgramRule::UndefinedCondition, ProgramRule::UndefinedCursor, ProgramRule::SignalConditionKind, ProgramRule::ZeroErrorCode, ProgramRule::DuplicateSignalItem, ProgramRule::DeclarationAfterCursorOrHandler, ProgramRule::CursorAfterHandler], true))) {
                return $problem;
            }
        }

        return null;
    }
}
