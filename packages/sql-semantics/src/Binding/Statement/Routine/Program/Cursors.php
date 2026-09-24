<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\Cursor\CursorCloseStatement;
use SqlSemantics\Model\Definition\Routine\Body\Cursor\CursorFetchStatement;
use SqlSemantics\Model\Definition\Routine\Body\Cursor\CursorOpenStatement;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Type\Nullability;

/**
 * Binds OPEN, FETCH and CLOSE against the cursors and variables declared in enclosing blocks.
 * @visibility SqlSemantics
 */
final class Cursors
{
    /**
     * Requires a declared cursor and, for FETCH, declared target variables.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Node $node, ProgramFrame $frame): CursorOpenStatement|CursorFetchStatement|CursorCloseStatement
    {
        $identifiers = $frame->context->tables->identifiers;
        $name = Tree::child($node, ['ident']) ?? throw new UnclassifiedSql('A cursor statement requires the cursor name.');
        $cursor = $identifiers->name($name->tokens()[0]);
        if (!isset($frame->cursors[strtolower($cursor)])) {
            throw new InvalidSql(InputViolation::ProgramObject, $name);
        }
        if ($node->name === 'sp_proc_stmt_open') {
            return new CursorOpenStatement($cursor);
        }
        if ($node->name === 'sp_proc_stmt_close') {
            return new CursorCloseStatement($cursor);
        }
        $targets = [];
        foreach (Tree::outer(Tree::child($node, ['sp_fetch_list']) ?? $node, ['ident']) as $target) {
            $targets[] = self::variable($target, $frame);
        }
        return new CursorFetchStatement($cursor, $targets);
    }

    /**
     * Resolves a declared local variable named as a statement target.
     * @throws InvalidSql
     */
    public static function variable(Node $target, ProgramFrame $frame): LocalVariableReference
    {
        $token = $target->tokens()[0] ?? throw new InvalidSql(InputViolation::ProgramObject, $target);
        $variable = $frame->names()->variable($frame->context->tables->identifiers->name($token)) ?? throw new InvalidSql(InputViolation::ProgramObject, $target);
        return new LocalVariableReference(new ExpressionFacts($variable->domain->type, Nullability::MaybeNull), $target, $variable);
    }
}
