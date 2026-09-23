<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\AlterForeignDataWrapperStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\CreateForeignDataWrapperStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds initial wrapper declarations separately from modifications to an existing definition.
 * @visibility SqlSemantics
 */
final class WrapperDeclarations
{
    /**
     * Leaves support-function execution and option interpretation to the consumer.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): CreateForeignDataWrapperStatement|AlterForeignDataWrapperStatement|null
    {
        if ($origin->dialect !== Dialect::PostgreSql || !in_array($source->name, ['CreateFdwStmt', 'AlterFdwStmt'], true)) {
            return null;
        }
        $name = Tree::child($source, ['name']) ?? throw new UnclassifiedSql('A wrapper declaration requires its name.');
        $identifiers = $context->tables->identifiers;
        [$handler, $validator] = WrapperOptions::functions($source, $identifiers);
        if ($source->name === 'AlterFdwStmt') {
            $changes = array_map(static fn (Node $node) => WrapperOptions::change($node, $identifiers), Tree::outer($source, ['alter_generic_option_elem']));
            return new AlterForeignDataWrapperStatement($origin, $identifiers->name($name->tokens()[0]), $handler, $validator, $changes);
        }
        $options = array_map(static fn (Node $node): ForeignOption => ForeignOperands::option($node, $identifiers), Tree::outer($source, ['generic_option_elem']));
        $names = array_map(static fn (ForeignOption $option): string => $option->name, $options);
        if (count(array_unique($names)) !== count($names)) {
            throw new InvalidSql(InputViolation::WrapperOption, $source);
        }
        return new CreateForeignDataWrapperStatement($origin, $identifiers->name($name->tokens()[0]), $handler instanceof QualifiedName ? $handler : null, $validator instanceof QualifiedName ? $validator : null, $options);
    }
}
