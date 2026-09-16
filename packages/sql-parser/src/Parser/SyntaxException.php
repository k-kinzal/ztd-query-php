<?php

declare(strict_types=1);

namespace SqlParser\Parser;

use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\SourcePosition;
use SqlParser\Lexer\Token;

/**
 * The parser met a token the grammar does not allow at that point.
 *
 * @visibility public
 *
 * @example Reading what the parser expected
 *     $end = new \SqlParser\Lexer\Token(0, '$end', '', 13);
 *     $e = new \SqlParser\Parser\SyntaxException($end, ['IDENT'], 'SELECT 1 FROM');
 *     $e->getMessage() // => 'Unexpected end of input at line 1, column 14, expected IDENT'
 *     $e->position->column // => 14
 */
final class SyntaxException extends SourceException
{
    /**
     * @param Token $token The token that was rejected
     * @param list<string> $expected Terminal names the parser would have accepted
     * @param string $source The SQL text
     */
    public function __construct(
        public readonly Token $token,
        public readonly array $expected,
        string $source,
    ) {
        $position = SourcePosition::at($source, $token->offset);
        $shown = $token->text === '' ? 'end of input' : "'" . $token->text . "'";
        $message = "Unexpected {$shown} at line {$position->line}, column {$position->column}";
        if ($expected !== []) {
            $message .= ', expected ' . implode(', ', array_slice($expected, 0, 8)) . (count($expected) > 8 ? ', ...' : '');
        }
        parent::__construct($message, $token->offset, $position);
    }
}
