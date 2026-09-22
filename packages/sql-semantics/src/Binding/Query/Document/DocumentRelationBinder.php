<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query\Document;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\BoundRelation;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\QueryNodes;
use SqlSemantics\Binding\Query\RelationFactory;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Relation\DocumentRelation;

/**
 * Gives JSON_TABLE and XMLTABLE their own relation occurrence and output namespace.
 * @visibility SqlSemantics
 */
final class DocumentRelationBinder
{
    /**
     * Returns null for an ordinary table-function call.
     */
    public static function bind(Node $source, Node $function, QueryContext $context, ?Scope $parent, string $scopeId): ?BoundRelation
    {
        if ($context->tables->identifiers->dialect === \SqlSemantics\Dialect::Sqlite || !in_array($function->name, ['json_table', 'xmltable', 'table_function'], true)) {
            return null;
        }
        $scope = $parent ?? new Scope($context->tables->identifiers, queries: $context);
        $table = $function->name === 'xmltable' ? XmlTableBinder::bind($function, $scope) : JsonTableBinder::bind($function, $scope);
        $aliasNode = QueryNodes::local($source, ['opt_alias_clause', 'alias_clause', 'opt_table_alias'])[0] ?? null;
        $names = $aliasNode === null ? [] : (new RelationFactory())->aliases($aliasNode, $context);
        $relation = new DocumentRelation($context->ids->relation(), $scopeId, $names[0] ?? null, $source, $table, array_slice($names, 1));
        return new BoundRelation($relation, new Scope($scope->identifiers, [$relation], parent: $parent, queries: $context));
    }
}
