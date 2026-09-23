<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\ServerVersionChange;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\PostgreSql\AlterForeignServerStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\CreateForeignServerStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds server creation and explicit metadata changes without opening a connection.
 * @visibility SqlSemantics
 */
final class ForeignServers
{
    /**
     * Classifies wrapper selection, server metadata, and option actions.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): CreateForeignServerStatement|AlterForeignServerStatement|null
    {
        if ($origin->dialect !== Dialect::PostgreSql || !in_array($source->name, ['CreateForeignServerStmt', 'AlterForeignServerStmt'], true)) {
            return null;
        }
        $identifiers = $context->tables->identifiers;
        $names = array_values(array_filter($source->children, static fn ($child): bool => $child instanceof Node && $child->name === 'name'));
        $name = $names[0] ?? throw new UnclassifiedSql('A foreign server requires a name.');
        $versionNode = Tree::outer($source, ['foreign_server_version'])[0] ?? null;
        $version = $versionNode === null ? null : self::text($versionNode);
        if ($source->name === 'AlterForeignServerStmt') {
            $options = array_map(static fn (Node $node) => WrapperOptions::change($node, $identifiers), Tree::outer($source, ['alter_generic_option_elem']));
            return new AlterForeignServerStatement($origin, $identifiers->name($name->tokens()[0]), $version ?? ($versionNode === null ? ServerVersionChange::Keep : ServerVersionChange::Remove), $options);
        }
        $wrapper = $names[1] ?? throw new UnclassifiedSql('A foreign server declaration requires a wrapper name.');
        $options = array_map(static fn (Node $node): ForeignOption => ForeignOperands::option($node, $identifiers), Tree::outer($source, ['generic_option_elem']));
        $optionNames = array_map(static fn (ForeignOption $option): string => $option->name, $options);
        if (count(array_unique($optionNames)) !== count($optionNames)) {
            throw new InvalidSql(InputViolation::ServerOption, $source);
        }
        $type = Tree::child($source, ['opt_type']);
        return new CreateForeignServerStatement($origin, $identifiers->name($name->tokens()[0]), $identifiers->name($wrapper->tokens()[0]), $type === null ? null : self::text($type), $version, $options, array_filter($source->children, static fn ($child): bool => $child instanceof \SqlParser\Lexer\Token && $child->name === 'IF_P') !== []);
    }

    /**
     * Reads an optional text operand, with VERSION NULL represented by absence.
     * @throws UnclassifiedSql
     */
    public static function text(Node $source): ?Literal
    {
        $text = Tree::outer($source, ['Sconst'])[0] ?? null;
        if ($text === null) {
            return null;
        }
        $value = (new LiteralBinder(Dialect::PostgreSql))->bind($text->tokens()[0]);
        return $value instanceof Literal ? $value : throw new UnclassifiedSql('Foreign server metadata requires a text literal.');
    }
}
