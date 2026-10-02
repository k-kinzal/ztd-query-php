<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Platform\Sqlite\IdentifierReader;
use SqlSemantics\Statement\Expression\Conversion\SqliteCast;
use SqlSemantics\Statement\Expression\Conversion\SqliteCollated;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Type\SqliteCastTarget;

/**
 * Reads explicit value conversions and collation choices in the original expression scope.
 * @visibility SqlSemantics
 */
final class ConversionReader
{
    /**
     * Selects conversion operations at their immediate expression boundary.
     */
    public function read(Node $source, Scope $scope, ExpressionReader $expressions): SqliteCast|SqliteCollated|null
    {
        $head = $source->children[0] ?? null;
        if ($head instanceof Token && \SqlSemantics\Statement\Identifier\Ascii::upper($head->text) === 'CAST') {
            return $this->cast($source, $scope, $expressions);
        }
        $operator = $source->children[1] ?? null;
        return $operator instanceof Token && \SqlSemantics\Statement\Identifier\Ascii::upper($operator->text) === 'COLLATE'
            ? $this->collated($source, $scope, $expressions)
            : null;
    }

    /**
     * CAST uses dequoted target text, not CREATE TABLE's native-name recognition rules.
     */
    public function cast(Node $source, Scope $scope, ExpressionReader $expressions): SqliteCast
    {
        Tree::assertChildren($source, ['expr', 'typetoken'], ['CAST', '(', 'AS', ')']);
        $operand = Tree::child($source, ['expr']);
        assert($operand !== null, 'A cast has one operand.');
        return new SqliteCast($expressions->read($operand, $scope), $this->target(Tree::child($source, ['typetoken'])));
    }

    /**
     * An explicit collation retains the nested operand rather than modifying its declared type.
     */
    public function collated(Node $source, Scope $scope, ExpressionReader $expressions): SqliteCollated
    {
        $children = Tree::significant($source);
        assert(count($children) === 3 && $children[0] instanceof Node && $children[0]->name === 'expr' && $children[1] instanceof Token && \SqlSemantics\Statement\Identifier\Ascii::upper($children[1]->text) === 'COLLATE' && $children[2] instanceof Token, 'COLLATE owns an operand and one collation name.');
        return new SqliteCollated($expressions->read($children[0], $scope), (new IdentifierReader())->name($children[2]));
    }

    /**
     * Decodes the type name SQLite supplies to its affinity calculation, including the empty name.
     */
    public function target(?Node $source): SqliteCastTarget
    {
        if ($source === null || $source->tokens() === []) {
            return new SqliteCastTarget('');
        }
        $tokens = $source->tokens();
        $first = (new IdentifierReader())->name($tokens[0]);
        $name = $first->quote === Quote::None ? substr($source->toString(), strlen($tokens[0]->leading)) : $first->value;
        return new SqliteCastTarget($name);
    }
}
