<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Catalog;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\PostgreSqlRoles;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog as Address;
use SqlSemantics\Model\Definition\Catalog\Kind\RelationKind;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog as Statement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\SetRelationSchemaStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Routes PostgreSQL catalog object commands by their grammar rule and object class.
 * @visibility SqlSemantics
 */
final class CatalogCommands
{
    /**
     * Returns null for other dialects and for statements outside the catalog, removal, and relation families.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            return null;
        }
        return match ($source->name) {
            'RenameStmt' => self::rename($origin, $source, $context),
            'AlterObjectSchemaStmt' => self::schema($origin, $source, $context),
            'AlterObjectDependsStmt' => self::dependency($origin, $source, $context),
            'AlterOwnerStmt' => new Statement\ChangeObjectOwnerStatement($origin, ObjectAddresses::read($source, $context), PostgreSqlRoles::read(Tree::child($source, ['RoleSpec']) ?? throw new UnclassifiedSql('An owner change requires its new owner.'))),
            'CommentStmt' => new Statement\CommentOnStatement($origin, ObjectAddresses::read($source, $context), self::text(Tree::child($source, ['comment_text']) ?? throw new UnclassifiedSql('A comment requires its text.'))),
            'SecLabelStmt' => self::label($origin, $source, $context),
            'DropStmt' => CatalogRemovals::bind($origin, $source, $context),
            'AlterTableStmt' => \SqlSemantics\Binding\Statement\Definition\Relation\RelationAlterations::bind($origin, $source, $context),
            'CreateForeignTableStmt' => \SqlSemantics\Binding\Statement\Definition\Relation\ForeignTables::bind($origin, $source, $context),
            default => null,
        };
    }

    /**
     * Roles and event triggers rename through their own families; relations carry existence and descendant policies.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function rename(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        $class = ObjectAddresses::objectClass($source);
        if (in_array($class, ['ROLE', 'USER', 'GROUP', 'EVENT TRIGGER'], true)) {
            return null;
        }
        $relation = RelationKind::tryFrom($class);
        if ($relation !== null) {
            return RelationTargets::rename($origin, $source, $context, $relation);
        }
        $identifiers = $context->tables->identifiers;
        $names = array_map(static fn (Node $node): string => $identifiers->name($node->tokens()[0]), Tree::outer($source, ['name']));
        $newName = $names[count($names) - 1] ?? throw new UnclassifiedSql('A rename requires its new name.');
        $words = ObjectAddresses::words($source);
        if ($class === 'DOMAIN' && in_array('CONSTRAINT', $words, true)) {
            return new Statement\RenameDomainConstraintStatement($origin, ObjectAddresses::name(Tree::child($source, ['any_name']) ?? throw new UnclassifiedSql('A domain constraint rename requires its domain.'), $context, 2), $names[0], $newName);
        }
        if ($class === 'TYPE' && in_array('ATTRIBUTE', $words, true)) {
            $behavior = Tree::child($source, ['opt_drop_behavior']);
            return new Statement\RenameTypeAttributeStatement($origin, ObjectAddresses::name(Tree::child($source, ['any_name']) ?? throw new UnclassifiedSql('An attribute rename requires its type.'), $context, 2), $names[0], $newName, $behavior === null ? DropBehavior::Default : DropBehavior::from(strtoupper(Tree::text($behavior))));
        }
        if ($class === 'POLICY') {
            $table = Tree::child($source, ['qualified_name']) ?? throw new UnclassifiedSql('A policy rename requires its table.');
            return new Statement\RenamePolicyStatement($origin, $names[0], ObjectAddresses::name($table, $context, 3), in_array('IF_P', array_map(static fn ($token): string => $token->name, $source->tokens()), true), $newName);
        }
        return new Statement\RenameObjectStatement($origin, ObjectAddresses::read($source, $context), $newName);
    }

    /**
     * Relations move with their existence policy; every other object moves by its identity alone.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function schema(Origin $origin, Node $source, QueryContext $context): BoundStatement
    {
        $schemaNode = Tree::outer($source, ['name']);
        $schema = $context->tables->identifiers->name(($schemaNode[count($schemaNode) - 1] ?? throw new UnclassifiedSql('A schema change requires its destination.'))->tokens()[0]);
        $relation = RelationKind::tryFrom(ObjectAddresses::objectClass($source));
        if ($relation !== null) {
            [$name, $ifExists, $only] = RelationTargets::read($source, $context);
            return new SetRelationSchemaStatement($origin, $relation, $name, $schema, $ifExists, $only);
        }
        return new Statement\SetObjectSchemaStatement($origin, ObjectAddresses::read($source, $context), $schema);
    }

    /**
     * NO DEPENDS removes the dependency that DEPENDS declares.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function dependency(Origin $origin, Node $source, QueryContext $context): BoundStatement
    {
        $object = ObjectAddresses::read($source, $context);
        if (!$object instanceof Address\RoutineIdentity && !$object instanceof Address\RelationMemberIdentity && !$object instanceof Address\RelationIdentity) {
            throw new UnclassifiedSql('An extension dependency requires a routine, trigger, materialized view, or index.');
        }
        $names = Tree::outer($source, ['name']);
        $extension = $context->tables->identifiers->name(($names[count($names) - 1] ?? throw new UnclassifiedSql('A dependency requires its extension.'))->tokens()[0]);
        return Tree::child($source, ['opt_no']) === null
            ? new Statement\AddExtensionDependencyStatement($origin, $object, $extension)
            : new Statement\RemoveExtensionDependencyStatement($origin, $object, $extension);
    }

    /**
     * The provider is optional; the label text is a string constant or NULL.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function label(Origin $origin, Node $source, QueryContext $context): Statement\SecurityLabelStatement
    {
        $provider = Tree::child($source, ['opt_provider']);
        $label = Tree::child($source, ['security_label']);
        $tokens = $source->tokens();
        $text = $label ?? $tokens[count($tokens) - 1];
        try {
            return new Statement\SecurityLabelStatement($origin, ObjectAddresses::read($source, $context), $provider === null ? null : ObjectAddresses::provider($provider->tokens()[1], $context), self::text($text));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::SecurityLabelTarget, $source, $error);
        }
    }

    /**
     * NULL removes the text; anything else is the literal with its original spelling.
     * @throws UnclassifiedSql
     */
    public static function text(Node|\SqlParser\Lexer\Token $source): ?Literal
    {
        $token = $source instanceof Node ? $source->tokens()[0] : $source;
        if (strtoupper($token->text) === 'NULL') {
            return null;
        }
        $literal = (new LiteralBinder(Dialect::PostgreSql))->bind($token);
        return $literal instanceof Literal ? $literal : throw new UnclassifiedSql('A comment or label requires a string constant.');
    }
}
