<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\ConditionalBranch;
use SqlSemantics\Model\Definition\Routine\Body\IfStatement;
use SqlSemantics\Model\Definition\Routine\Body\SearchedCaseStatement;
use SqlSemantics\Model\Definition\Routine\Body\SimpleCaseStatement;

/**
 * Binds IF and CASE statements with their ordered branches.
 * @visibility SqlSemantics
 */
final class ControlFlow
{
    /**
     * Flattens the nested ELSEIF chain into ordered branches and an optional ELSE list.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function if(Node $node, ProgramFrame $frame): IfStatement
    {
        $branches = [];
        $otherwise = [];
        $branch = Tree::child($node, ['sp_if']);
        while ($branch !== null) {
            $branches[] = self::branch($branch, 'sp_proc_stmts1', $frame);
            $rest = Tree::child($branch, ['sp_elseifs']);
            $branch = $rest === null ? null : Tree::child($rest, ['sp_if']);
            if ($rest !== null && $branch === null) {
                $otherwise = ProgramBinder::list(Tree::child($rest, ['sp_proc_stmts1']), $frame);
            }
        }
        return new IfStatement($branches, $otherwise);
    }

    /**
     * Distinguishes a simple CASE with an operand from a searched CASE.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function case(Node $node, ProgramFrame $frame): SimpleCaseStatement|SearchedCaseStatement
    {
        $form = Tree::child($node, ['simple_case_stmt', 'searched_case_stmt']) ?? throw new UnclassifiedSql('A CASE statement requires its form.');
        $whens = array_map(static fn (Node $when): ConditionalBranch => self::branch($when, 'sp_proc_stmts1', $frame), Tree::outer($form, ['simple_when_clause', 'searched_when_clause']));
        $else = Tree::child($form, ['else_clause_opt']);
        $otherwise = ProgramBinder::list($else === null ? null : Tree::child($else, ['sp_proc_stmts1']), $frame);
        if ($form->name === 'simple_case_stmt') {
            return new SimpleCaseStatement($frame->expression(Tree::child($form, ['expr']) ?? throw new UnclassifiedSql('A simple CASE requires its operand.')), $whens, $otherwise);
        }
        return new SearchedCaseStatement($whens, $otherwise);
    }

    /**
     * Binds one guard and its statements.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function branch(Node $node, string $list, ProgramFrame $frame): ConditionalBranch
    {
        $condition = Tree::child($node, ['expr']) ?? throw new UnclassifiedSql('A branch requires its condition.');
        return new ConditionalBranch($frame->expression($condition), ProgramBinder::list(Tree::child($node, [$list]), $frame));
    }
}
