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
    public function completed(Node $node, GrammarRelease $release): ?Deprecated
    {
        $first = $node->children[0] ?? null;
        $assignment = $node->children[$node->name === 'variable_aux' ? 1 : 2] ?? null;
        $construct = match (true) {
            $node->name === 'text_literal' && $first instanceof Token && $first->name === 'NCHAR_STRING' => Deprecated::National,
            $node->name === 'simple_expr' && $first instanceof Token && in_array($first->name, ['BINARY', 'BINARY_SYM'], true) => Deprecated::BinaryOperator,
            in_array($node->name, ['variable_aux', 'in_expression_user_variable_assignment'], true) && $assignment instanceof Token && $assignment->name === 'SET_VAR' => Deprecated::AssignmentInExpression,
            default => null,
        };

        return $construct !== null && $construct->warnedIn($release) ? $construct : null;
    }
}
