<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\View;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Schema\StorageParameters;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\View as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds PostgreSQL materialized view declaration, refresh, and removal as distinct forms.
 * @visibility SqlSemantics
 */
final class MaterializedViewBinder
{
    /**
     * Returns null for statements outside the materialized view family.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            return null;
        }
        $words = array_map(static fn ($token): string => strtoupper($token->text), $source->tokens());
        if (!in_array('MATERIALIZED', array_slice($words, 0, 3), true)) {
            return null;
        }
        return match ($words[0]) {
            'CREATE' => self::create($origin, $source, $context, $words),
            'REFRESH' => self::refresh($origin, $source, $context, $words),
            'DROP' => self::drop($origin, $source, $context, $words),
            default => null,
        };
    }

    /**
     * @param list<string> $words
     * @throws UnclassifiedSql
     */
    public static function create(Origin $origin, Node $source, QueryContext $context, array $words): Statement\CreateMaterializedViewStatement
    {
        $target = Tree::child($source, ['create_mv_target']) ?? throw new UnclassifiedSql('A materialized view declaration requires its target.');
        $query = Tree::child($source, ['SelectStmt']) ?? throw new UnclassifiedSql('A materialized view declaration requires its query.');
        $identifiers = $context->tables->identifiers;
        $name = Tree::child($target, ['qualified_name']) ?? throw new UnclassifiedSql('A materialized view declaration requires its name.');
        $method = Tree::child($target, ['table_access_method_clause']);
        $tablespace = Tree::child($target, ['OptTableSpace']);
        return new Statement\CreateMaterializedViewStatement(
            $origin,
            new QualifiedName($identifiers->parts($name)),
            $context->bind($query),
            ViewBinder::columns($target, $context),
            in_array('UNLOGGED', $words, true),
            in_array('IF', $words, true),
            $method === null ? null : $identifiers->name($method->tokens()[1]),
            StorageParameters::read($target, new Scope($identifiers, queries: $context)),
            $tablespace === null ? null : $identifiers->name($tablespace->tokens()[1]),
            !str_contains(strtoupper(Tree::text($source)), 'WITH NO DATA'),
        );
    }

    /**
     * @param list<string> $words
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function refresh(Origin $origin, Node $source, QueryContext $context, array $words): Statement\RefreshMaterializedViewStatement
    {
        $name = Tree::child($source, ['qualified_name']) ?? throw new UnclassifiedSql('A materialized view refresh requires its name.');
        try {
            return new Statement\RefreshMaterializedViewStatement($origin, new QualifiedName($context->tables->identifiers->parts($name)), in_array('CONCURRENTLY', $words, true), !str_contains(strtoupper(Tree::text($source)), 'WITH NO DATA'));
        } catch (InvalidStructure $violation) {
            throw new InvalidSql(InputViolation::ConcurrentEmptyRefresh, $source, $violation);
        }
    }

    /**
     * @param list<string> $words
     * @throws UnclassifiedSql
     */
    public static function drop(Origin $origin, Node $source, QueryContext $context, array $words): Statement\DropMaterializedViewsStatement
    {
        $names = array_map(fn (Node $name): QualifiedName => new QualifiedName($context->tables->identifiers->parts($name)), Tree::outer($source, ['any_name']));
        if ($names === []) {
            throw new UnclassifiedSql('A materialized view removal requires its names.');
        }
        return new Statement\DropMaterializedViewsStatement($origin, $names, in_array('IF', $words, true), DropBehavior::tryFrom($words[count($words) - 1]) ?? DropBehavior::Default);
    }
}
