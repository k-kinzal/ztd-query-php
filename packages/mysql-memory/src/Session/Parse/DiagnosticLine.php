<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use SqlParser\Lexer\SourceException;
use SqlParser\MySql\MySqlParser;
use SqlParser\Parser\SqlParser;

/**
 * Counts the line displayed by an input syntax diagnostic.
 *
 * Legacy MySQL does not advance that line for newlines inside a quoted
 * identifier. Modern releases include them. Leading statement whitespace
 * does not advance the first line. Observed on 5.6.51 through 9.1.0.
 *
 * @visibility MySqlMemory
 */
final class DiagnosticLine
{
    /**
     * Answers a diagnostic line without retaining the SQL or its tokens.
     */
    public function at(string $statement, int $offset, bool $legacy, ?SqlParser $parser = null): int
    {
        $line = substr_count(ltrim(substr($statement, 0, $offset)), "\n") + 1;
        if (!$legacy) {
            return $line;
        }
        try {
            $tokens = ($parser ?? new MySqlParser('mysql-5.7.44'))->tokenize($statement);
        } catch (SourceException) {
            return $line;
        }
        foreach ($tokens as $token) {
            if ($token->name === 'IDENT_QUOTED' && $token->end() <= $offset) {
                $line -= substr_count($token->text, "\n");
            }
        }

        return $line;
    }
}
