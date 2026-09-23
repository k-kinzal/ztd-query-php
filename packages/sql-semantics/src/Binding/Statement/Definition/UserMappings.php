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
use SqlSemantics\Model\Definition\Foreign\UserMappingIdentity;
use SqlSemantics\Model\Statement\Definition\PostgreSql as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds creation, option modification, and removal of foreign user mappings separately.
 * @visibility SqlSemantics
 */
final class UserMappings
{
    /**
     * Requires the mapping's role selector and foreign-server identity.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): Statement\CreateUserMappingStatement|Statement\AlterUserMappingStatement|Statement\DropUserMappingStatement|null
    {
        if ($origin->dialect !== Dialect::PostgreSql || !in_array($source->name, ['CreateUserMappingStmt', 'AlterUserMappingStmt', 'DropUserMappingStmt'], true)) {
            return null;
        }
        $user = Tree::child($source, ['auth_ident']) ?? throw new UnclassifiedSql('A user mapping requires its role selector.');
        $server = Tree::child($source, ['name']) ?? throw new UnclassifiedSql('A user mapping requires its foreign server.');
        $identifiers = $context->tables->identifiers;
        $target = new UserMappingIdentity(MappingUsers::read($user), $identifiers->name($server->tokens()[0]));
        $existence = array_filter($source->children, static fn ($child): bool => $child instanceof \SqlParser\Lexer\Token && $child->name === 'IF_P') !== [];
        if ($source->name === 'DropUserMappingStmt') {
            return new Statement\DropUserMappingStatement($origin, $target, $existence);
        }
        if ($source->name === 'AlterUserMappingStmt') {
            $options = array_map(static fn (Node $node) => WrapperOptions::change($node, $identifiers), Tree::outer($source, ['alter_generic_option_elem']));
            return new Statement\AlterUserMappingStatement($origin, $target, $options);
        }
        $options = array_map(static fn (Node $node): ForeignOption => ForeignOperands::option($node, $identifiers), Tree::outer($source, ['generic_option_elem']));
        $names = array_map(static fn (ForeignOption $option): string => $option->name, $options);
        if (count(array_unique($names)) !== count($names)) {
            throw new InvalidSql(InputViolation::MappingOption, $source);
        }
        return new Statement\CreateUserMappingStatement($origin, $target, $options, $existence);
    }
}
