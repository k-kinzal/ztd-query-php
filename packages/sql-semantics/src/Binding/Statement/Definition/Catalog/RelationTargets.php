<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Catalog;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Definition\Catalog\Kind\RelationKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation as Statement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Reads the relation, its existence policy, and its descendant policy from the head of a relation command.
 * @visibility SqlSemantics
 */
final class RelationTargets
{
    /**
     * @return array{QualifiedName, bool, bool} Name, IF EXISTS, and ONLY
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\InvalidSql
     */
    public static function read(Node $source, QueryContext $context): array
    {
        $ifExists = false;
        $relation = null;
        foreach ($source->children as $child) {
            if ($child instanceof Node && in_array($child->name, ['relation_expr', 'qualified_name'], true)) {
                $relation = $child;
                break;
            }
            $ifExists = $ifExists || ($child instanceof Token && $child->name === 'IF_P');
        }
        if ($relation === null) {
            throw new UnclassifiedSql('A relation command requires its relation.');
        }
        $extended = Tree::child($relation, ['extended_relation_expr']);
        $only = $extended !== null && strtoupper($extended->tokens()[0]->text) === 'ONLY';
        return [ObjectAddresses::relation($relation, $context), $ifExists, $only];
    }

    /**
     * Distinguishes relation, column, and constraint renames by their operand count and keyword.
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\InvalidSql
     */
    public static function rename(Origin $origin, Node $source, QueryContext $context, RelationKind $kind): Statement\RenameRelationStatement|Statement\RenameRelationColumnStatement|Statement\RenameTableConstraintStatement
    {
        [$name, $ifExists, $only] = self::read($source, $context);
        $names = array_map(static fn (Node $node): string => $context->tables->identifiers->name($node->tokens()[0]), Tree::outer($source, ['name']));
        $newName = $names[count($names) - 1] ?? throw new UnclassifiedSql('A rename requires its new name.');
        if (count($names) === 1) {
            return new Statement\RenameRelationStatement($origin, $kind, $name, $newName, $ifExists, $only);
        }
        $constraint = array_filter($source->children, static fn ($child): bool => $child instanceof Token && $child->name === 'CONSTRAINT') !== [];
        return $constraint
            ? new Statement\RenameTableConstraintStatement($origin, $name, $names[0], $newName, $ifExists, $only)
            : new Statement\RenameRelationColumnStatement($origin, $kind, $name, $names[0], $newName, $ifExists, $only);
    }
}
