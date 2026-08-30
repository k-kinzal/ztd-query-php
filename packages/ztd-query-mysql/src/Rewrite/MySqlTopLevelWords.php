<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite;

/**
 * The words a statement writes outside everything it writes them around.
 *
 * A WITH prefix carries a body in parentheses that may be written in any
 * dialect the parser refuses, but what the statement then goes on to do is
 * still written plainly after it. Reading only the words outside every quote
 * and parenthesis is what tells that word from the same word inside the body.
 */
final class MySqlTopLevelWords
{
    private const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Answers the words written after the first parenthesised body.
     *
     * @param string $sql The statement, as written
     *
     * @return list<string> The words, upper-cased, in the order they were written
     */
    public function afterBody(string $sql): array
    {
        $written = strtoupper($sql);
        $length = strlen($written);
        $words = [];
        $depth = 0;
        $seenBody = false;
        $quote = '';

        for ($offset = 0; $offset < $length; $offset++) {
            $character = $written[$offset];
            if ($quote !== '') {
                $quote = $this->closesQuote($written, $offset, $quote) ? '' : $quote;
                continue;
            }
            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
                continue;
            }
            if ($character === '(') {
                $depth++;
                $seenBody = true;
                continue;
            }
            if ($character === ')') {
                $depth = max(0, $depth - 1);
                continue;
            }
            if (!$seenBody || $depth !== 0 || !ctype_alpha($character)) {
                continue;
            }
            if ($offset > 0 && ctype_alpha($written[$offset - 1])) {
                continue;
            }
            $end = $offset + strspn($written, self::LETTERS, $offset);
            $words[] = substr($written, $offset, $end - $offset);
            $offset = max($offset, $end - 1);
        }

        return $words;
    }

    /**
     * Reports whether the character closes the run a quote opened.
     *
     * A backtick is never escaped, so a second one always closes the run; any
     * other quote written after a backslash stands for itself.
     *
     * @param string $sql The statement, as written
     * @param int $offset Where to look
     * @param string $quote The quote that opened the run
     *
     * @return bool True when the run ends there
     */
    public function closesQuote(string $sql, int $offset, string $quote): bool
    {
        if ($sql[$offset] !== $quote) {
            return false;
        }

        return $quote === '`' || $offset === 0 || $sql[$offset - 1] !== '\\';
    }
}
