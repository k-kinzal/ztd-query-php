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
                $problem = $this->diagnostic($frame->body(), $parameters);
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
     * Adds a completed declaration or statement, preserving a parameter-list refusal.
     */
    public function completed(Node $node, PrefixFrame $frame, ParameterList $parameters): ParameterList|RejectedInput
    {
        if (in_array($node->name, ['sp_pdparam_list', 'sp_fdparam_list'], true)) {
            try {
                return (new ParameterRule($this->lowering))->parameters($node);
            } catch (RejectedInput $failure) {
                return $failure;
            }
        }
        if ($node->name === 'sp_decl') {
            $frame->declarations[] = (new BlockRule($this->lowering))->declaration($node);
        } else {
            $frame->statements[] = (new StatementRule($this->lowering))->statement($node);
        }

        return $parameters;
    }

    /**
     * Resolves prefix scopes using the ordinary rules for declarations and condition names.
     */
    public function diagnostic(Block $body, ParameterList $parameters): ?Diagnostic
    {
        $derivation = new Derivation(new AnalysisContext($this->lowering->profile, [new Name(SessionDatabase::UNNAMED)], complete: false, columnNames: Comparison::AsciiInsensitive));
        (new ProgramFacts())->routine($parameters, $body, ProgramKind::Procedure, new QualifiedName(new Name('prefix')), $derivation);
        foreach ($derivation->facts()->diagnostics as $problem) {
            if ($problem instanceof UnknownSystemVariable || ($problem instanceof ProgramProblem && in_array($problem->rule, [ProgramRule::DuplicateVariable, ProgramRule::DuplicateCondition, ProgramRule::DuplicateCursor, ProgramRule::DuplicateHandler, ProgramRule::DuplicateParameter, ProgramRule::UndefinedCondition, ProgramRule::SignalConditionKind, ProgramRule::ZeroErrorCode, ProgramRule::DuplicateSignalItem, ProgramRule::DeclarationAfterCursorOrHandler, ProgramRule::CursorAfterHandler], true))) {
                return $problem;
            }
        }

        return null;
    }
}
