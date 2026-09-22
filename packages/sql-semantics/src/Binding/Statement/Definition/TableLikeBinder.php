<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Table\CreateTableLikeStatement;

/**
 * Resolves the target identity and required source of a MySQL definition-copy operation.
 * @visibility SqlSemantics
 */
final class TableLikeBinder
{
    /**
     * Returns null when the table definition is supplied by another CREATE form.
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?CreateTableLikeStatement
    {
        if ($origin->dialect !== Dialect::MySql) {
            return null;
        }
        $body = Tree::child($source, ['create2']) ?? $source;
        $like = array_filter($body->children, static fn ($child): bool => $child instanceof \SqlParser\Lexer\Token && strtoupper($child->text) === 'LIKE');
        $names = Tree::outer($source, ['table_ident']);
        if (count($names) !== 2 || $like === []) {
            return null;
        }
        $words = array_map(static fn ($token): string => strtoupper($token->text), $source->tokens());
        $target = $context->tables->identifiers->parts($names[0]);
        if (count($target) === 1 && $context->tables->defaultSchema !== '') {
            array_unshift($target, $context->tables->defaultSchema);
        }
        $parts = $context->tables->identifiers->parts($names[1]);
        $table = $context->tables->resolve($parts, $names[1]);
        $template = new TableReference($context->ids->relation(), $origin->scopeId, $table, $context->tables->name($parts, $table), null, $names[1]);
        return new CreateTableLikeStatement($origin, new QualifiedName($target), $template, in_array('TEMPORARY', $words, true), in_array('IF', $words, true));
    }
}
