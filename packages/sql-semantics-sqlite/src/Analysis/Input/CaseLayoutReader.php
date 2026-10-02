<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis\Input;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Statement\Expression\Rendering\SqliteCaseArmLayout;
use SqlSemantics\Statement\Expression\Rendering\SqliteCaseLayout;
use SqlSemantics\Statement\Expression\Rendering\SqliteElseLayout;
use SqlSemantics\Statement\Validation\Check;

/**
 * Decodes only bounded CASE keywords and inter-symbol trivia, never an operand fragment.
 * @visibility SqlSemantics
 */
final class CaseLayoutReader
{
    /**
     * Keeps delimiter case and the actual gaps beside the optional base and final END.
     */
    public function operation(Node $source): SqliteCaseLayout
    {
        $tokens = array_values(array_filter($source->children, static fn (Node|Token $child): bool => $child instanceof Token));
        Check::invariant(count($tokens) === 2, 'A CASE has exactly its two outer keyword terminals.');
        $base = Tree::child($source, ['case_operand']);
        return new SqliteCaseLayout($tokens[0]->text, $tokens[1]->text, $base === null ? ' ' : $base->tokens()[0]->leading, $tokens[1]->leading);
    }

    /**
     * Associates WHEN/THEN separators with the two expression slots in this actual arm.
     */
    public function arm(Node $source): SqliteCaseArmLayout
    {
        $tokens = array_values(array_filter($source->children, static fn (Node|Token $child): bool => $child instanceof Token));
        $operands = array_values(array_filter($source->children, static fn (Node|Token $child): bool => $child instanceof Node && $child->name === 'expr'));
        Check::invariant(count($tokens) === 2 && count($operands) === 2, 'A CASE arm has two keywords and two actual operands.');
        return new SqliteCaseArmLayout($tokens[0]->text, $tokens[1]->text, $tokens[0]->leading, $operands[0]->tokens()[0]->leading, $tokens[1]->leading, $operands[1]->tokens()[0]->leading);
    }

    /**
     * An absent ELSE does not produce a synthetic default expression.
     */
    public function otherwise(?Node $source): SqliteElseLayout
    {
        if ($source === null) {
            return new SqliteElseLayout();
        }
        $keyword = $source->children[0] ?? null;
        $operand = Tree::child($source, ['expr']);
        Check::invariant($keyword instanceof Token && $operand !== null, 'ELSE has one keyword and its actual result operand.');
        return new SqliteElseLayout($keyword->text, $keyword->leading, $operand->tokens()[0]->leading);
    }
}
