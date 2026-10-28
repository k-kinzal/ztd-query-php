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
        $this->lowering->parameters($tree->tokens());
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
            if ($completed && in_array($node->name, ['sp_decl_idents', 'sp_decl', 'sp_proc_stmt', 'ev_sql_stmt_inner', 'sp_pdparam_list', 'sp_fdparam_list', 'signal_value', 'signal_information_item_list'], true)) {
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
     * @throws \SqlSemantics\Diagnostic\AnalysisException When a completed instruction contains a syntax refusal
     */
    public function completed(Node $node, PrefixFrame $frame, ParameterList $parameters): ParameterList|RejectedInput
    {
        $name = (new \SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxTableNames())->within($node, $this->lowering);
        if ($name !== null) {
            return $name;
        }
        try {
            if (in_array($node->name, ['signal_value', 'signal_information_item_list'], true)) {
                $signal = new \SqlSemantics\Platform\MySql\Lowering\Routine\Condition\SignalRule($this->lowering);
                $frame->statements[] = $node->name === 'signal_value' ? new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal($signal->condition($node)) : new \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal(null, $signal->items($node));
                return $parameters;
            }
            if ($node->name === 'sp_decl_idents') {
                return $this->variables($node, $frame) ?? $parameters;
            }
            if (in_array($node->name, ['sp_pdparam_list', 'sp_fdparam_list'], true)) {
                return (new ParameterRule($this->lowering))->parameters($node);
            }
            if ($node->name === 'sp_decl') {
                $list = $this->lowering->form($node)->node(1);
                $conflict = $list->name === 'sp_decl_idents' ? $this->variables($list, $frame) : null;
                if ($conflict !== null) {
                    return $conflict;
                }
                $frame->declarations[] = (new BlockRule($this->lowering))->declaration($node);
            } else {
                $frame->statements[] = (new StatementRule($this->lowering))->statement($node);
            }
        } catch (RejectedInput $failure) {
            return $failure;
        }

        return $parameters;
    }

    /**
     * Checks completed names against this block before reading the remaining declaration.
     */
    public function variables(Node $list, PrefixFrame $frame): ?RejectedInput
    {
        $names = [];
        $rules = new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\DeclarationNames();
        foreach ($frame->declarations as $declaration) {
            if ($declaration instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration) {
                $rules->names($declaration->names, $names, ProgramRule::DuplicateVariable);
            }
        }
        $problem = $rules->names((new BlockRule($this->lowering))->names($list, 'sp_decl_idents'), $names, ProgramRule::DuplicateVariable);
        return $problem === null ? null : new RejectedInput($problem, (new SourceRanges())->span($list)[1] ?? 0);
    }

    /**
     * Resolves prefix scopes using the ordinary rules for declarations and condition names.
     */
    public function diagnostic(Block $body, ParameterList $parameters, bool $scopeComplete = true): ?Diagnostic
    {
        $body = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($body);
        $parameters = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($parameters);
        $derivation = new Derivation(new AnalysisContext($this->lowering->profile, [new Name(SessionDatabase::UNNAMED)], complete: false, columnNames: Comparison::AsciiInsensitive));
        (new ProgramFacts())->routine($parameters, $body, ProgramKind::Procedure, new QualifiedName(new Name('prefix')), $derivation, scopeComplete: $scopeComplete);
        $facts = $derivation->facts();
        $parsed = array_map(static fn ($warning): Diagnostic => $warning->problem, array_filter($facts->warnings, static fn ($warning): bool => $warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure && $warning->phase === \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax));
        foreach ($facts->diagnostics as $problem) {
            if (in_array($problem, $parsed, true)) {
                return $problem;
            }
            if ($problem instanceof UnknownSystemVariable || $problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral || ($problem instanceof ProgramProblem && in_array($problem->rule, [ProgramRule::BadSqlState, ProgramRule::IncorrectName, ProgramRule::NameTooLong, ProgramRule::DuplicateVariable, ProgramRule::DuplicateCondition, ProgramRule::DuplicateCursor, ProgramRule::DuplicateHandler, ProgramRule::DuplicateParameter, ProgramRule::UndefinedCondition, ProgramRule::UndefinedCursor, ProgramRule::SignalConditionKind, ProgramRule::ZeroErrorCode, ProgramRule::DuplicateSignalItem, ProgramRule::DeclarationAfterCursorOrHandler, ProgramRule::CursorAfterHandler], true))) {
                return $problem;
            }
        }

        return null;
    }
}
