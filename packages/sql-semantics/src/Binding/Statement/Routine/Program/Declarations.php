<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\Retrieval\IntoPlacement;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\ConditionDeclaration;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\CursorDeclaration;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\HandlerAction;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\HandlerDeclaration;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\VariableDeclaration;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds one DECLARE of a block and returns the frame in which later declarations and statements are bound.
 * @visibility SqlSemantics
 */
final class Declarations
{
    /**
     * @param array<string, true> $handled Conditions handled by earlier handlers of the block, updated in place
     * @return array{VariableDeclaration|ConditionDeclaration|CursorDeclaration|HandlerDeclaration, ProgramFrame}
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Node $node, ProgramFrame $frame, array &$handled): array
    {
        $identifiers = $frame->context->tables->identifiers;
        $tokens = $node->tokens();
        $second = strtoupper($tokens[2]->text ?? '');
        if (Tree::child($node, ['sp_handler_type']) !== null) {
            $action = HandlerAction::from(strtoupper($tokens[1]->text));
            $conditions = HandlerConditions::list(Tree::child($node, ['sp_hcond_list']) ?? $node, $frame, $handled);
            $statement = ProgramBinder::statement(Tree::child($node, ['sp_proc_stmt']) ?? throw new UnclassifiedSql('A handler requires its statement.'), $frame->inHandler());
            return [new HandlerDeclaration($action, $conditions, $statement), $frame];
        }
        $name = Tree::child($node, ['ident']);
        if ($second === 'CONDITION' && $name !== null) {
            $value = HandlerConditions::value(Tree::child($node, ['sp_cond']) ?? throw new UnclassifiedSql('A condition requires its value.'), $frame);
            $condition = $identifiers->name($name->tokens()[0]);
            return [new ConditionDeclaration($condition, $value), $frame->withCondition($condition, $value)];
        }
        if ($second === 'CURSOR' && $name !== null) {
            $query = Tree::child($node, ['select_stmt', 'select']) ?? throw new UnclassifiedSql('A cursor requires its query.');
            if (IntoPlacement::clauses($query) !== []) {
                throw new InvalidSql(InputViolation::ProgramDeclaration, $query);
            }
            $cursor = $identifiers->name($name->tokens()[0]);
            return [new CursorDeclaration($cursor, $frame->context->bind($query)), $frame->withCursor($cursor)];
        }
        $names = array_map(static fn (Node $ident): string => $identifiers->name($ident->tokens()[0]), Tree::outer(Tree::child($node, ['sp_decl_idents']) ?? throw new UnclassifiedSql('A variable declaration requires names.'), ['ident']));
        if (count(array_unique(array_map(strtolower(...), $names))) !== count($names)) {
            throw new InvalidSql(InputViolation::ProgramDeclaration, $node);
        }
        $domain = Domains::read($node, $identifiers);
        $declaration = new VariableDeclaration($names, $domain);
        $inner = $frame->withVariables($declaration->variables());
        $default = Tree::child($node, ['sp_opt_default']);
        return [new VariableDeclaration($names, $domain, $default === null ? null : $inner->expression(Tree::child($default, ['expr']) ?? throw new UnclassifiedSql('DEFAULT requires its expression.'))), $inner];
    }
}
