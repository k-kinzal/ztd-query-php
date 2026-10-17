<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;

/**
 * Finds the statements of a text the client sent that the server runs before one that does not parse.
 *
 * The server reads a text of several statements one statement at a time: it runs those that
 * parse, then fails at the first that does not, quoting the text from there to the end of the
 * text, later statements included (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0
 * servers).
 * Source: https://dev.mysql.com/doc/c-api/8.4/en/c-api-multiple-queries.html.
 *
 * @visibility MySqlMemory
 */
final class Script
{
    /**
     * Answers the statements before the first that does not parse, then the rest of the text, which fails when it runs; or an empty list when the text fails at its first statement.
     *
     * @return list<string>
     */
    public function statements(Semantics $semantics, string $sql): array
    {
        $end = strrpos($sql, ';');
        while ($end !== false) {
            try {
                $statements = $semantics->split(substr($sql, 0, $end + 1));
            } catch (AnalysisException) {
                $end = $end === 0 ? false : strrpos($sql, ';', $end - strlen($sql) - 1);
                continue;
            }
            $statements[] = substr($sql, $end + 1);

            return $statements;
        }

        return [];
    }
}
