<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis\Input;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Statement\Construction\Conditional\CaseArmInput;
use SqlSemantics\Statement\Construction\Conditional\CaseBranchesInput;
use SqlSemantics\Statement\Construction\Conditional\SearchedCaseInput;
use SqlSemantics\Statement\Construction\Conditional\SimpleCaseInput;

/**
 * Gives the two CASE evaluation strategies distinct semantic operation types.
 * @visibility SqlSemantics
 */
final class CaseInputReader
{
    /**
     * Reads only a CASE at this immediate expression boundary, keeping enclosing operators separate.
     */
    public function read(Node $source, ExpressionInputReader $expressions): SearchedCaseInput|SimpleCaseInput|null
    {
        $head = $source->children[0] ?? null;
        if (!$head instanceof Token || strtoupper($head->text) !== 'CASE') {
            return null;
        }
        Tree::assertChildren($source, ['case_operand', 'case_exprlist', 'case_else'], ['CASE', 'END']);
        $arms = Tree::child($source, ['case_exprlist']);
        assert($arms !== null, 'A CASE expression has at least one WHEN branch.');
        $otherwise = Tree::child($source, ['case_else']);
        $branches = new CaseBranchesInput($otherwise === null ? null : $expressions->read(Tree::outer($otherwise, ['expr'])[0]), ...$this->arms($arms, $expressions));
        $base = Tree::child($source, ['case_operand']);
        return $base === null
            ? new SearchedCaseInput($branches)
            : new SimpleCaseInput($expressions->read(Tree::outer($base, ['expr'])[0]), $branches);
    }

    /**
     * Keeps each WHEN paired with its THEN result and preserves the order of tests.
     * @return non-empty-list<CaseArmInput>
     */
    public function arms(Node $source, ExpressionInputReader $expressions): array
    {
        Tree::assertChildren($source, ['case_exprlist', 'expr'], ['WHEN', 'THEN']);
        $prefix = Tree::child($source, ['case_exprlist']);
        $arms = $prefix === null ? [] : $this->arms($prefix, $expressions);
        $operands = array_values(array_filter($source->children, static fn (Node|Token $child): bool => $child instanceof Node && $child->name === 'expr'));
        assert(count($operands) === 2, 'Each branch has exactly one test and one result.');
        $arms[] = new CaseArmInput($expressions->read($operands[0]), $expressions->read($operands[1]));
        return $arms;
    }
}
