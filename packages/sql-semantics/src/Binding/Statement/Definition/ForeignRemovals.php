<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Definition\PostgreSql\DropForeignDataWrappersStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\DropForeignServersStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;

/**
 * Binds server and wrapper removal as separate operations with their own target lists.
 * @visibility SqlSemantics
 */
final class ForeignRemovals
{
    /**
     * Retains existence and dependency policies without looking up remote objects.
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): DropForeignServersStatement|DropForeignDataWrappersStatement|null
    {
        if ($origin->dialect !== Dialect::PostgreSql || $source->name !== 'DropStmt') {
            return null;
        }
        $kind = Tree::child($source, ['drop_type_name']);
        $operation = $kind === null ? '' : strtoupper(Tree::text($kind));
        if (!in_array($operation, ['SERVER', 'FOREIGN DATA WRAPPER'], true)) {
            return null;
        }
        $names = Collections::nonEmpty(array_map(static fn (Node $name): string => $context->tables->identifiers->name($name->tokens()[0]), Tree::outer($source, ['name'])));
        $behavior = Tree::child($source, ['opt_drop_behavior']);
        $policy = $behavior === null ? DropBehavior::Default : DropBehavior::from(strtoupper(Tree::text($behavior)));
        $ifExists = array_filter($source->children, static fn ($child): bool => $child instanceof \SqlParser\Lexer\Token && $child->name === 'IF_P') !== [];
        return $operation === 'SERVER' ? new DropForeignServersStatement($origin, $names, $ifExists, $policy) : new DropForeignDataWrappersStatement($origin, $names, $ifExists, $policy);
    }
}
