<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Statement\Query\SqliteLimit;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;

/**
 * Resolves count and skip roles in an expression scope without SELECT input relations.
 * @visibility SqlSemantics
 */
final class LimitReader
{
    /**
     * Comma notation reverses the two expression roles; OFFSET notation does not.
     */
    public function read(Node $source, Catalog $catalog): SqliteLimit
    {
        Tree::assertChildren($source, ['expr'], ['LIMIT', 'OFFSET', ',']);
        $scope = new Scope($catalog);
        $reader = new ExpressionReader();
        $operands = Tree::outer($source, ['expr']);
        assert(count($operands) === 1 || count($operands) === 2, 'A row restriction has a count and optionally an offset.');
        $comma = count(array_filter($source->children, static fn (Node|Token $child): bool => $child instanceof Token && $child->text === ',')) !== 0;
        $first = $reader->read($operands[0], $scope);
        $second = isset($operands[1]) ? $reader->read($operands[1], $scope) : null;
        if ($comma) {
            assert($second !== null, 'Comma notation has skip and count expressions.');
            return new SqliteLimit($scope, $second, $first, true);
        }
        return new SqliteLimit($scope, $first, $second);
    }
}
