<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Semantic\Projection\Field;
use SqlSemantics\Semantic\Projection\Fields;
use SqlSemantics\Semantic\Projection\Star;
use SqlSemantics\Semantic\QualifiedName;
use SqlSemantics\Semantic\Scope;

/**
 * Reads ordered result expressions, aliases, and explicit stars.
 * @visibility SqlSemantics
 */
final class ProjectionReader
{
    /**
     * Lowers the supplied syntax and rejects any unmodeled semantic operation.
     */
    public function read(Node $select, Scope $scope): Fields
    {
        $items = $scope->dialect->platform()->query()->projectionItems($select);
        if ($items === []) {
            $list = Tree::child($select, $scope->dialect->platform()->syntax()->nodes('projectionList'));
            if ($list !== null && Tree::text($list) === '*') {
                return new Fields($scope, new Star($scope));
            }
            Tree::unsupported($select, 'empty projection');
        }
        $fields = array_map(fn (Node $item): Field|Star => $this->item($item, $scope), $items);
        return new Fields($scope, $fields[0], ...array_slice($fields, 1));
    }

    /**
     * Reads a projected expression, its alias, or an explicit star.
     */
    public function item(Node $item, Scope $scope): Field|Star
    {
        $syntax = $scope->dialect->platform()->syntax();
        $expression = Tree::child($item, $syntax->nodes('projectionExpression'));
        $alias = Tree::child($item, $syntax->nodes('projectionAlias'));
        $tokens = $scope->dialect->platform()->query()->projectionTokens($item, $expression);
        $names = new Names(new Identifiers($scope->dialect));
        if ($tokens !== [] && $tokens[count($tokens) - 1]->text === '*') {
            if (count($tokens) === 1) {
                return new Star($scope);
            }
            if (!in_array(count($tokens), [3, 5], true)) {
                Tree::unsupported($item, 'star qualifier');
            }
            $qualifier = new QualifiedName($names->name($tokens[count($tokens) - 3]), count($tokens) === 5 ? $names->name($tokens[0]) : null);
            return new Star($scope, $qualifier);
        }
        if ($expression === null) {
            Tree::unsupported($item, 'projection expression');
        }
        $aliasTokens = $alias?->tokens() ?? [];
        $name = $aliasTokens === [] ? null : $names->name($aliasTokens[count($aliasTokens) - 1]);
        return new Field((new ExpressionReader())->read($expression, $scope), $name);
    }
}
