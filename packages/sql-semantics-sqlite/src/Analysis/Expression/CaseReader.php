<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis\Expression;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Platform\Sqlite\Analysis\ExpressionReader;
use SqlSemantics\Statement\Expression\Conditional\SqliteCaseArm;
use SqlSemantics\Statement\Expression\Conditional\SqliteCaseBranches;
use SqlSemantics\Statement\Expression\Conditional\SqliteSearchedCase;
use SqlSemantics\Statement\Expression\Conditional\SqliteSimpleCase;
use SqlSemantics\Statement\Relation\Scope;

/**
 * Gives the two CASE evaluation strategies distinct semantic operation types.
 * @visibility SqlSemantics
 */
final class CaseReader
{
    /**
     * Reads only a CASE at this immediate expression boundary, keeping enclosing operators separate.
     */
    public function read(Node $source, Scope $scope, ExpressionReader $expressions): SqliteSearchedCase|SqliteSimpleCase|null
    {
        $head = $source->children[0] ?? null;
        if (!$head instanceof Token || strtoupper($head->text) !== 'CASE') {
            return null;
        }
        Tree::assertChildren($source, ['case_operand', 'case_exprlist', 'case_else'], ['CASE', 'END']);
        $arms = Tree::child($source, ['case_exprlist']);
        assert($arms !== null, 'A CASE expression has at least one WHEN branch.');
        $otherwise = Tree::child($source, ['case_else']);
        $branches = new SqliteCaseBranches($otherwise === null ? null : $expressions->read(Tree::outer($otherwise, ['expr'])[0], $scope), (new \SqlSemantics\Platform\Sqlite\Analysis\Input\CaseLayoutReader())->otherwise($otherwise), ...$this->arms($arms, $scope, $expressions));
        $base = Tree::child($source, ['case_operand']);
        return $base === null
            ? new SqliteSearchedCase($branches, (new \SqlSemantics\Platform\Sqlite\Analysis\Input\CaseLayoutReader())->operation($source))
            : new SqliteSimpleCase($expressions->read(Tree::outer($base, ['expr'])[0], $scope), $branches, (new \SqlSemantics\Platform\Sqlite\Analysis\Input\CaseLayoutReader())->operation($source));
    }

    /**
     * Keeps each WHEN paired with its THEN result and preserves the order of tests.
     * @return non-empty-list<SqliteCaseArm>
     */
    public function arms(Node $source, Scope $scope, ExpressionReader $expressions): array
    {
        Tree::assertChildren($source, ['case_exprlist', 'expr'], ['WHEN', 'THEN']);
        $prefix = Tree::child($source, ['case_exprlist']);
        $arms = $prefix === null ? [] : $this->arms($prefix, $scope, $expressions);
        $operands = array_values(array_filter($source->children, static fn (Node|Token $child): bool => $child instanceof Node && $child->name === 'expr'));
        assert(count($operands) === 2, 'Each branch has exactly one test and one result.');
        $arms[] = new SqliteCaseArm($expressions->read($operands[0], $scope), $expressions->read($operands[1], $scope), (new \SqlSemantics\Platform\Sqlite\Analysis\Input\CaseLayoutReader())->arm($source));
        return $arms;
    }
}
