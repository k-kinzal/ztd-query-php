<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Extensibility;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Extension as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds extension installation, update, and membership changes, procedural languages, and access methods.
 * @visibility SqlSemantics
 */
final class Extensions
{
    /**
     * CREATE EXTENSION [IF NOT EXISTS] name [WITH] [SCHEMA s] [VERSION v] [CASCADE]; each option is given at most once and FROM is no longer supported.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function create(Origin $origin, Node $source, QueryContext $context): Statement\CreateExtensionStatement
    {
        $options = [];
        foreach (Tree::outer($source, ['create_extension_opt_item']) as $item) {
            $keyword = strtoupper($item->tokens()[0]->text);
            if ($keyword === 'FROM' || array_key_exists($keyword, $options)) {
                throw new InvalidSql(InputViolation::ExtensionOption, $item);
            }
            $argument = $item->tokens()[1] ?? null;
            $options[$keyword] = $argument === null ? '' : self::word($argument, $context);
        }
        $ifNotExists = in_array('EXISTS', self::leading($source), true);
        try {
            return new Statement\CreateExtensionStatement($origin, self::name($source, $context), $ifNotExists, $options['SCHEMA'] ?? null, $options['VERSION'] ?? null, array_key_exists('CASCADE', $options));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ExtensionOption, $source, $error);
        }
    }

    /**
     * ALTER EXTENSION name UPDATE [TO version]; the target version is given at most once.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function update(Origin $origin, Node $source, QueryContext $context): Statement\UpdateExtensionStatement
    {
        $items = Tree::outer($source, ['alter_extension_opt_item']);
        if (count($items) > 1) {
            throw new InvalidSql(InputViolation::ExtensionOption, $items[1]);
        }
        $version = $items === [] ? null : self::word($items[0]->tokens()[1] ?? throw new UnclassifiedSql('UPDATE TO requires a version.'), $context);
        try {
            return new Statement\UpdateExtensionStatement($origin, self::name($source, $context), $version);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ExtensionOption, $source, $error);
        }
    }

    /**
     * ALTER EXTENSION name ADD | DROP object, where the object is addressed as in the other catalog commands.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function member(Origin $origin, Node $source, QueryContext $context): Statement\AddExtensionMemberStatement|Statement\DropExtensionMemberStatement
    {
        $children = $source->children;
        $start = null;
        foreach ($children as $index => $child) {
            if ($child instanceof Node && $child->name === 'add_drop') {
                $start = $index;
            }
        }
        if ($start === null) {
            throw new UnclassifiedSql('An extension membership change requires ADD or DROP.');
        }
        $member = new Node($source->name, $source->ordinal, array_slice($children, $start));
        $object = ObjectAddresses::read($member, $context);
        $extension = self::name($source, $context);
        try {
            return strtoupper(Tree::text($children[$start])) === 'ADD'
                ? new Statement\AddExtensionMemberStatement($origin, $extension, $object)
                : new Statement\DropExtensionMemberStatement($origin, $extension, $object);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::CatalogObjectName, $source, $error);
        }
    }

    /**
     * CREATE [OR REPLACE] [TRUSTED] [PROCEDURAL] LANGUAGE name HANDLER h [INLINE i] [VALIDATOR v | NO VALIDATOR].
     * Without HANDLER the server installs the extension of that name, so the request binds as CREATE EXTENSION.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function language(Origin $origin, Node $source, QueryContext $context): Statement\CreateLanguageStatement|Statement\CreateExtensionStatement
    {
        $name = self::name($source, $context);
        $orReplace = Tree::child($source, ['opt_or_replace']) !== null;
        $handlers = Tree::outer($source, ['handler_name']);
        try {
            if ($handlers === []) {
                return new Statement\CreateExtensionStatement($origin, $name, $orReplace, null, null, false);
            }
            $inline = Tree::child($source, ['opt_inline_handler']);
            $validator = Tree::child(Tree::child($source, ['opt_validator']) ?? $source, ['validator_clause']);
            $validatorName = $validator === null ? null : Tree::child($validator, ['handler_name']);
            return new Statement\CreateLanguageStatement(
                $origin,
                $name,
                $orReplace,
                Tree::child($source, ['opt_trusted']) !== null,
                self::function($handlers[0], $context),
                $inline === null ? null : self::function(Tree::child($inline, ['handler_name']) ?? throw new UnclassifiedSql('INLINE requires a handler.'), $context),
                $validatorName === null ? null : self::function($validatorName, $context),
            );
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::CatalogObjectName, $source, $error);
        }
    }

    /**
     * CREATE ACCESS METHOD name TYPE { TABLE | INDEX } HANDLER function.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function accessMethod(Origin $origin, Node $source, QueryContext $context): Statement\CreateAccessMethodStatement
    {
        $type = Statement\AccessMethodKind::from(strtoupper(Tree::text(Tree::child($source, ['am_type']) ?? throw new UnclassifiedSql('An access method requires its type.'))));
        $handler = self::function(Tree::child($source, ['handler_name']) ?? throw new UnclassifiedSql('An access method requires its handler.'), $context);
        try {
            return new Statement\CreateAccessMethodStatement($origin, self::name($source, $context), $type, $handler);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::CatalogObjectName, $source, $error);
        }
    }

    /**
     * Returns the uppercased keywords written before the object name, where IF NOT EXISTS appears.
     * @return list<string>
     */
    public static function leading(Node $source): array
    {
        $words = [];
        foreach ($source->children as $child) {
            if ($child instanceof Node && $child->name === 'name') {
                break;
            }
            if ($child instanceof Token) {
                $words[] = strtoupper($child->text);
            }
        }
        return $words;
    }

    /**
     * Reads the object name that follows the object class keyword.
     * @throws UnclassifiedSql
     */
    public static function name(Node $source, QueryContext $context): string
    {
        $name = Tree::child($source, ['name']) ?? throw new UnclassifiedSql('The definition requires its name.');
        return $context->tables->identifiers->name($name->tokens()[0]);
    }

    /**
     * A handler function is a name with optional qualifying attributes, at most a database, a schema, and a function name.
     * @throws InvalidSql
     */
    public static function function(Node $source, QueryContext $context): QualifiedName
    {
        return ObjectAddresses::name($source, $context, 3);
    }

    /**
     * Decodes a bare word or a string constant.
     */
    public static function word(Token $token, QueryContext $context): string
    {
        return ObjectAddresses::provider($token, $context);
    }
}
