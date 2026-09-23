<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\FunctionChange;
use SqlSemantics\Model\Definition\Foreign\SetForeignOption;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Classifies support-function changes and value-bearing versus name-only option changes.
 * @visibility SqlSemantics
 */
final class WrapperOptions
{
    /**
     * @return array{QualifiedName|FunctionChange, QualifiedName|FunctionChange} Handler and validator changes
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function functions(Node $source, Identifiers $identifiers): array
    {
        $functions = ['HANDLER' => FunctionChange::Keep, 'VALIDATOR' => FunctionChange::Keep];
        $seen = [];
        foreach (Tree::outer($source, ['fdw_option']) as $option) {
            $tokens = $option->tokens();
            $remove = strtoupper($tokens[0]->text) === 'NO';
            $kind = strtoupper($tokens[$remove ? 1 : 0]->text);
            if (!array_key_exists($kind, $functions)) {
                throw new UnclassifiedSql('A wrapper support function must be a handler or validator.');
            }
            if (isset($seen[$kind])) {
                throw new InvalidSql(InputViolation::WrapperFunction, $option);
            }
            $seen[$kind] = true;
            $name = Tree::child($option, ['handler_name']);
            if (!$remove && $name === null) {
                throw new UnclassifiedSql('A supplied support function requires its name.');
            }
            $parts = $name === null ? [] : $identifiers->parts($name);
            if (count($parts) > 3 || in_array('', $parts, true)) {
                throw new InvalidSql(InputViolation::RoutineName, $option);
            }
            $functions[$kind] = $remove ? FunctionChange::Remove : new QualifiedName($parts);
        }
        return [$functions['HANDLER'], $functions['VALIDATOR']];
    }

    /**
     * A missing action denotes ADD; DROP has no value operand.
     * @throws UnclassifiedSql
     */
    public static function change(Node $source, Identifiers $identifiers): AddForeignOption|SetForeignOption|DropForeignOption
    {
        $action = $source->children[0] instanceof \SqlParser\Lexer\Token ? strtoupper($source->children[0]->text) : 'ADD';
        if ($action === 'DROP') {
            $name = Tree::child($source, ['generic_option_name']) ?? throw new UnclassifiedSql('A removed option requires its name.');
            return new DropForeignOption($identifiers->name($name->tokens()[0]));
        }
        $option = ForeignOperands::option(Tree::child($source, ['generic_option_elem']) ?? throw new UnclassifiedSql('An added or replaced option requires its name and value.'), $identifiers);
        return $action === 'SET' ? new SetForeignOption($option) : new AddForeignOption($option);
    }
}
