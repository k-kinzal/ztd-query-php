<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\IterateStatement;
use SqlSemantics\Model\Definition\Routine\Body\LeaveStatement;
use SqlSemantics\Model\Definition\Routine\Body\LoopStatement;
use SqlSemantics\Model\Definition\Routine\Body\RepeatStatement;
use SqlSemantics\Model\Definition\Routine\Body\WhileStatement;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds LOOP, WHILE and REPEAT with their labels, and the LEAVE and ITERATE statements that target labels.
 * @visibility SqlSemantics
 */
final class Loops
{
    /**
     * Binds a labeled or unlabeled loop; its label is visible to the statements it repeats.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Node $node, ProgramFrame $frame): LoopStatement|WhileStatement|RepeatStatement
    {
        $control = $node->name === 'sp_unlabeled_control' ? $node : (Tree::child($node, ['sp_unlabeled_control']) ?? throw new UnclassifiedSql('A labeled loop requires its body.'));
        $label = self::label($node, $frame);
        $inner = $label === null ? $frame : $frame->withLabel($label, true);
        $statements = ProgramBinder::list(Tree::child($control, ['sp_proc_stmts1']), $inner);
        $condition = Tree::child($control, ['expr']);
        return match (strtoupper($control->tokens()[0]->text ?? '')) {
            'WHILE' => new WhileStatement($label, $frame->expression($condition ?? throw new UnclassifiedSql('WHILE requires its condition.')), $statements),
            'REPEAT' => new RepeatStatement($label, $statements, $frame->expression($condition ?? throw new UnclassifiedSql('REPEAT requires its condition.'))),
            default => new LoopStatement($label, $statements),
        };
    }

    /**
     * Reads a begin label, which no enclosing block or loop may use, and requires a matching end label.
     * @throws InvalidSql
     */
    public static function label(Node $node, ProgramFrame $frame): ?string
    {
        $begin = Tree::child($node, ['label_ident']);
        if ($begin === null) {
            return null;
        }
        $identifiers = $frame->context->tables->identifiers;
        $label = $identifiers->name($begin->tokens()[0]);
        $end = Tree::child($node, ['sp_opt_label']);
        if (isset($frame->labels[strtolower($label)]) || ($end !== null && strcasecmp($identifiers->name($end->tokens()[0]), $label) !== 0)) {
            throw new InvalidSql(InputViolation::ProgramLabel, $end ?? $begin);
        }
        return $label;
    }

    /**
     * Binds LEAVE for an enclosing label and ITERATE for an enclosing loop.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function jump(Node $node, ProgramFrame $frame): LeaveStatement|IterateStatement
    {
        $target = Tree::child($node, ['label_ident']) ?? throw new UnclassifiedSql('LEAVE and ITERATE require a label.');
        $label = $frame->context->tables->identifiers->name($target->tokens()[0]);
        $loop = $frame->labels[strtolower($label)] ?? null;
        $leave = $node->name === 'sp_proc_stmt_leave';
        if ($loop === null || (!$leave && !$loop)) {
            throw new InvalidSql(InputViolation::ProgramLabel, $target);
        }
        return $leave ? new LeaveStatement($label) : new IterateStatement($label);
    }
}
