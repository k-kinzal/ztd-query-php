<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\ProgramStatement;
use SqlSemantics\Model\Definition\Routine\Body\ReturnStatement;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds the statements of a stored program body, one semantic class per compound, flow-control and cursor construct.
 * @visibility SqlSemantics
 */
final class ProgramBinder
{
    /**
     * Binds one body statement in the given frame.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function statement(Node $node, ProgramFrame $frame): ProgramStatement
    {
        $node = self::unwrap($node);
        return match ($node->name) {
            'sp_proc_stmt_statement' => Embedded::bind($node, $frame),
            'sp_proc_stmt_return' => self::return($node, $frame),
            'sp_proc_stmt_if' => ControlFlow::if($node, $frame),
            'case_stmt_specification' => ControlFlow::case($node, $frame),
            'sp_labeled_block', 'sp_unlabeled_block' => Blocks::bind($node, $frame),
            'sp_labeled_control', 'sp_unlabeled_control' => Loops::bind($node, $frame),
            'sp_proc_stmt_leave', 'sp_proc_stmt_iterate' => Loops::jump($node, $frame),
            'sp_proc_stmt_open', 'sp_proc_stmt_fetch', 'sp_proc_stmt_close' => Cursors::bind($node, $frame),
            default => throw new UnclassifiedSql('Unclassified stored program statement: ' . $node->name),
        };
    }

    /**
     * Binds the statements of a statement list in order; a missing list has none.
     * @return list<ProgramStatement>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function list(?Node $list, ProgramFrame $frame): array
    {
        return $list === null ? [] : array_map(static fn (Node $statement): ProgramStatement => self::statement($statement, $frame), Tree::outer($list, ['sp_proc_stmt']));
    }

    /**
     * Descends through grammar wrappers that add no meaning to the statement they contain.
     */
    public static function unwrap(Node $node): Node
    {
        while (in_array($node->name, ['sp_proc_stmt', 'ev_sql_stmt', 'ev_sql_stmt_inner', 'sp_proc_stmt_unlabeled', 'stored_routine_body', 'opt_ev_sql_stmt'], true)) {
            $children = array_values(array_filter(Tree::significant($node), static fn ($child): bool => $child instanceof Node));
            if ($children === []) {
                return $node;
            }
            $node = $children[0];
        }
        return $node;
    }

    /**
     * Binds RETURN, which only a stored function may contain.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function return(Node $node, ProgramFrame $frame): ReturnStatement
    {
        if ($frame->kind !== ProgramKind::Function) {
            throw new InvalidSql(InputViolation::ProgramStatement, $node);
        }
        return new ReturnStatement($frame->expression(Tree::child($node, ['expr']) ?? throw new UnclassifiedSql('RETURN requires its expression.')));
    }
}
