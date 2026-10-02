<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis\Input;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Platform\Sqlite\Analysis\LiteralExpressionReader;
use SqlSemantics\Platform\Sqlite\IdentifierReader;
use SqlSemantics\Statement\Construction\Expression\BetweenInput;
use SqlSemantics\Statement\Construction\Expression\BinaryInput;
use SqlSemantics\Statement\Construction\Expression\ColumnUse;
use SqlSemantics\Statement\Construction\Expression\InListInput;
use SqlSemantics\Statement\Construction\Expression\UnaryInput;
use SqlSemantics\Statement\Construction\ScalarInput;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Expression\SqliteBinaryOperator;
use SqlSemantics\Statement\Expression\SqliteUnaryOperator;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation\Scope;

/**
 * Resolves scalar operations and their column references in one semantic scope.
 * @visibility SqlSemantics
 */
final class ExpressionInputReader
{
    /**
     * Separates numeric and NULL constants from names with possible truth alternatives.
     */
    public function read(Node $source): ScalarInput
    {
        $conversion = (new SubqueryInputReader())->read($source, $this) ?? (new ConversionInputReader())->read($source, $this) ?? (new CaseInputReader())->read($source, $this);
        if ($conversion !== null) {
            return $conversion;
        }
        $term = Tree::child($source, ['term']);
        if ($term !== null) {
            assert(count($term->tokens()) === 1, 'A literal term has one terminal.');
            return (new LiteralExpressionReader())->read($term->tokens()[0]);
        }
        $children = Tree::significant($source);
        if (count($children) === 3 && $children[0] instanceof Token && $children[0]->text === '(' && $children[1] instanceof Node && $children[1]->name === 'expr' && $children[2] instanceof Token && $children[2]->text === ')') {
            return new \SqlSemantics\Statement\Construction\Expression\GroupedInput($this->read($children[1]), $children[1]->tokens()[0]->leading, $children[2]->leading);
        }
        if (count($children) === 2 && $children[0] instanceof Token && $children[1] instanceof Node && $children[1]->name === 'expr') {
            $operator = SqliteUnaryOperator::tryFrom(strtoupper($children[0]->text));
            if ($operator !== null) {
                return new UnaryInput($operator, $this->read($children[1]), new \SqlSemantics\Statement\Expression\Rendering\SqliteUnaryLayout($operator, $children[0]->text, $children[1]->tokens()[0]->leading, false));
            }
        }
        $operation = $this->infix($source) ?? $this->predicate($source);
        if ($operation !== null) {
            return $operation;
        }
        return $this->reference($source);
    }

    /**
     * Applies column, alias, and truth-name lookup in their database-defined order.
     */
    public function reference(Node $source): ScalarInput
    {
        return $this->column($source);
    }

    /**
     * Resolves built-in binary operations while preserving operand order.
     */
    public function infix(Node $source): ?BinaryInput
    {
        $children = Tree::significant($source);
        $first = $children[0] ?? null;
        $last = $children[count($children) - 1] ?? null;
        if ($first instanceof Node && $first->name === 'expr' && $last instanceof Node && $last->name === 'expr' && count($children) >= 3) {
            $operator = SqliteBinaryOperator::tryFrom(strtoupper(implode(' ', array_map(Tree::text(...), array_slice($children, 1, -1)))));
            if ($operator !== null) {
                $operatorTokens = array_merge(...array_map(static fn (Node|Token $node): array => $node instanceof Node ? $node->tokens() : [$node], array_slice($children, 1, -1)));
                $firstToken = $operatorTokens[0];
                $spelling = $firstToken->text . implode('', array_map(static fn (Token $token): string => $token->leading . $token->text, array_slice($operatorTokens, 1)));
                $layout = new \SqlSemantics\Statement\Expression\Rendering\SqliteBinaryLayout($operator, $spelling, $firstToken->leading, $last->tokens()[0]->leading, false);
                return new BinaryInput($this->read($first), $operator, $this->read($last), $layout);
            }
        }
        return null;
    }

    /**
     * Keeps membership and range tests distinct from repeated comparisons.
     */
    public function predicate(Node $source): BinaryInput|BetweenInput|InListInput|null
    {
        $operands = array_values(array_filter($source->children, static fn (Node|Token $child): bool => $child instanceof Node && $child->name === 'expr'));
        $between = Tree::child($source, ['between_op']);
        if ($between !== null) {
            assert(count($operands) === 3, 'A range has a subject and two bounds.');
            return new BetweenInput($this->read($operands[0]), $this->read($operands[1]), $this->read($operands[2]), str_starts_with(strtoupper(Tree::text($between)), 'NOT '));
        }
        $membership = Tree::child($source, ['in_op']);
        if ($membership !== null && Tree::child($source, ['select', 'nm']) === null) {
            Tree::assertChildren($source, ['expr', 'in_op', 'exprlist'], ['(', ')']);
            assert(count($operands) === 1, 'A scalar list membership has one subject.');
            $list = Tree::child($source, ['exprlist']);
            $choices = $list === null ? [] : array_map(fn (Node $node): ScalarInput => $this->read($node), Tree::outer($list, ['expr']));
            return new InListInput($this->read($operands[0]), str_starts_with(strtoupper(Tree::text($membership)), 'NOT '), ...$choices);
        }
        $children = Tree::significant($source);
        $suffix = strtoupper(implode(' ', array_map(Tree::text(...), array_slice($children, 1))));
        if (count($operands) === 1 && in_array($suffix, ['ISNULL', 'NOTNULL', 'NOT NULL'], true)) {
            return new BinaryInput($this->read($operands[0]), $suffix === 'ISNULL' ? SqliteBinaryOperator::Is : SqliteBinaryOperator::IsNot, new NullConstant());
        }
        return null;
    }

    /**
     * Reads a column lookup without treating other expression forms as names.
     */
    public function column(Node $source): ColumnUse
    {
        $children = Tree::significant($source);
        if (count($children) === 1 && $children[0] instanceof Token && in_array($children[0]->name, ['ID', 'INDEXED', 'JOIN_KW'], true)) {
            $parts = [$children[0]];
        } else {
            Tree::assertChildren($source, ['nm'], ['.']);
            $parts = Tree::outer($source, ['nm']);
        }
        assert(count($parts) >= 1 && count($parts) <= 3, 'A column has up to three name positions.');
        $names = array_map((new IdentifierReader())->name(...), $parts);
        $name = $names[count($names) - 1];
        $qualifier = count($names) === 1 ? null : new QualifiedName($names[count($names) - 2], count($names) === 3 ? $names[0] : null);
        return new ColumnUse($name, $qualifier);
    }

}
