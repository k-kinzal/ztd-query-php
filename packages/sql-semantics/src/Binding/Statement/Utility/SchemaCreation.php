<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\PostgreSqlRoles;
use SqlSemantics\Binding\Configuration\SettingTokens;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\StatementBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Schema as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds CREATE SCHEMA and its elements inside the schema being created.
 * @visibility SqlSemantics
 */
final class SchemaCreation
{
    /**
     * Separates named schemas from schemas named after their owner.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): Statement\CreateSchemaStatement|Statement\CreateAuthorizationSchemaStatement
    {
        $identifiers = $context->tables->identifiers;
        $label = Tree::child($source, ['opt_single_name', 'ColId']);
        $spec = Tree::child($source, ['RoleSpec']);
        $owner = $spec === null ? null : PostgreSqlRoles::read($spec);
        $name = $label === null ? ($owner instanceof NamedRole ? $owner->name : null) : $identifiers->name($label->tokens()[0]);
        $ifNotExists = array_slice(SettingTokens::words($source->tokens()), 2, 3) === ['IF', 'NOT', 'EXISTS'];
        $elements = self::elements($source, $name, $context);
        if ($ifNotExists && $elements !== []) {
            throw new InvalidSql(InputViolation::SchemaElement, $source);
        }
        try {
            if ($label !== null) {
                return new Statement\CreateSchemaStatement($origin, $identifiers->name($label->tokens()[0]), $owner, $ifNotExists, $elements);
            }
            return new Statement\CreateAuthorizationSchemaStatement($origin, $owner ?? throw new UnclassifiedSql('CREATE SCHEMA requires a name or an owner.'), $ifNotExists, $elements);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::SchemaElement, $source, $error);
        }
    }

    /**
     * Binds each element without a default schema, so unqualified names stay relative to the schema being created.
     * @return list<BoundStatement>
     * @throws InvalidSql
     */
    public static function elements(Node $source, ?string $schema, QueryContext $context): array
    {
        $resolver = new TableResolver($context->tables->schema, $context->tables->identifiers, '', $context->tables->diagnostics);
        $nested = new QueryContext($resolver, $context->ids);
        $result = [];
        foreach (Tree::outer($source, ['schema_stmt']) as $element) {
            $command = Tree::significant($element)[0] ?? null;
            if (!$command instanceof Node) {
                continue;
            }
            self::placement($command, $schema, $context);
            $result[] = (new StatementBinder($resolver))->node($command, $command, $nested);
        }
        return $result;
    }

    /**
     * Requires a permanent object whose explicit schema, if any, is the schema being created.
     * @throws InvalidSql
     */
    public static function placement(Node $command, ?string $schema, QueryContext $context): void
    {
        if ($command->name === 'GrantStmt') {
            return;
        }
        $words = array_slice(SettingTokens::words($command->tokens()), 1, 4);
        $name = Tree::outer($command, ['qualified_name'])[0] ?? null;
        $parts = $name === null ? [] : $context->tables->identifiers->parts($name);
        $qualifier = $parts[count($parts) - 2] ?? null;
        if (in_array('TEMP', $words, true) || in_array('TEMPORARY', $words, true) || ($qualifier !== null && $schema !== null && $qualifier !== $schema)) {
            throw new InvalidSql(InputViolation::SchemaElement, $command);
        }
    }
}
