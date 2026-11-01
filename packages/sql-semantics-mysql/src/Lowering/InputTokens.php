<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering;

use SqlParser\Lexer\LexicalException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\SqlParser;

/**
 * Reads the tokenizable prefix before the first lexical failure.
 *
 * An eager lexer may first report a later invalid byte. Each retry removes
 * that suffix until the remaining prefix can be inspected. No source text
 * or token is retained in a semantic structure.
 *
 * @visibility public
 * @example Inspecting the prefix before several invalid bytes
 *     $parser = new \SqlParser\MySql\MySqlParser('mysql-8.4.7');
 *     (new \SqlSemantics\Platform\MySql\Lowering\InputTokens())->prefix($parser, "SELECT 1 ]\x1e")[0]->text // => 'SELECT'
 */
final class InputTokens
{
    /**
     * Returns the tokens and end marker before the earliest lexical refusal.
     *
     * @return list<Token>
     */
    public function prefix(SqlParser $parser, string $statement): array
    {
        while (true) {
            try {
                return $parser->tokenize($statement);
            } catch (LexicalException $failure) {
                $statement = substr($statement, 0, $failure->offset);
            }
        }
    }
}
