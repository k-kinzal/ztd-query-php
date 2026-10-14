<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

/**
 * Identifies deprecations at the completion of expression productions.
 *
 * Operand completion distinguishes BINARY and assignment notices from
 * notices triggered by a leading operator. National strings warn once
 * at the first text-literal reduction, before later literal segments.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxDeprecations
{
    /**
     * Answers a deprecation raised on completion of its grammar production.
     */
    public function completed(Node $node, GrammarRelease $release): Deprecated|\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation|null
    {
        $first = $node->children[0] ?? null;
        $assignment = $node->children[$node->name === 'variable_aux' ? 1 : 2] ?? null;
        $construct = match (true) {
            $node->name === 'text_literal' && $first instanceof Token && $first->name === 'NCHAR_STRING' => Deprecated::National,
            $node->name === 'simple_expr' && $first instanceof Token && in_array($first->name, ['BINARY', 'BINARY_SYM'], true) => Deprecated::BinaryOperator,
            in_array($node->name, ['variable_aux', 'in_expression_user_variable_assignment'], true) && $assignment instanceof Token && $assignment->name === 'SET_VAR' => Deprecated::AssignmentInExpression,
            default => null,
        };

        if ($construct === Deprecated::BinaryOperator && $construct->warnedIn($release) && $release !== GrammarRelease::MySql8044 && $this->stopsBinary($node)) {
            return new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation($construct, aborts: true);
        }

        return $construct !== null && $construct->warnedIn($release) ? $construct : null;
    }
    /**
     * Recognizes a BINARY operand that consists of AT LOCAL and optional grouping only.
     * Arithmetic and other enclosing expressions remain distinct operands.
     */
    public function stopsBinary(Node $node): bool
    {
        $operand = $node->children[1] ?? null;
        while ($operand instanceof Node) {
            $first = $operand->children[0] ?? null;
            if (count($operand->children) === 1 && $first instanceof Node) {
                $operand = $first;
                continue;
            }
            $last = $operand->children[2] ?? null;
            if (count($operand->children) === 3 && $first instanceof Token && $first->text === '(' && $last instanceof Token && $last->text === ')') {
                $operand = $operand->children[1];
                continue;
            }
            $local = $operand->children[4] ?? null;

            return $operand->name === 'simple_expr' && $first instanceof Token && $first->name === 'CAST_SYM' && $local instanceof Token && $local->name === 'LOCAL_SYM';
        }

        return false;
    }

}
