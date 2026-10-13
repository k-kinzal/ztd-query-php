<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering;

use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

/**
 * Interprets warnings of completed expressions before a later input refusal.
 *
 * BINARY and assignment expressions warn after their operand has been read.
 * A missing operand therefore produces no notice. The temporary parser nodes
 * are consumed during the call; only warning descriptors and offsets escape.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-warnings.html.
 *
 * @visibility public
 * @example Preserving a completed BINARY warning before an unprepared marker
 *     $parser = new \SqlParser\MySql\MySqlParser('mysql-8.4.7');
 *     $notices = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed($parser, 'SELECT BINARY 1,?', 16, \SqlSemantics\Contract\GrammarRelease::MySql847);
 *     array_column($notices, 1) // => [\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BinaryOperator]
 */
final class SyntaxWarnings
{
    /**
     * Returns completion notices raised before the token at the refusal offset.
     *
     * @return list<array{int, Deprecated}> Input boundaries and warning descriptors
     */
    public function completed(ReductionParser $parser, string $statement, int $end, GrammarRelease $release): array
    {
        $warnings = [];
        $ranges = new SourceRanges();
        try {
            $parser->parse($statement, static function (Node $node, Token $lookahead) use (&$warnings, $ranges, $end, $release): void {
                if ($lookahead->offset > $end) {
                    return;
                }
                $first = $node->children[0] ?? null;
                $assignment = $node->children[$node->name === 'variable_aux' ? 1 : 2] ?? null;
                $construct = match (true) {
                    $node->name === 'text_literal' && $first instanceof Token && $first->name === 'NCHAR_STRING' => Deprecated::National,
                    $node->name === 'simple_expr' && $first instanceof Token && in_array($first->name, ['BINARY', 'BINARY_SYM'], true) => Deprecated::BinaryOperator,
                    in_array($node->name, ['variable_aux', 'in_expression_user_variable_assignment'], true) && $assignment instanceof Token && $assignment->name === 'SET_VAR' => Deprecated::AssignmentInExpression,
                    default => null,
                };
                if ($construct !== null && $construct->warnedIn($release)) {
                    $range = $ranges->span($node, true);
                    if ($range !== null) {
                        $warnings[] = [$range[1], $construct];
                    }
                }
            });
        } catch (SourceException) {
            // Completed reductions precede the original refusal; the caller owns that error.
        }

        return $warnings;
    }
}
