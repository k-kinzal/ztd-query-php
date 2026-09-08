<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Lexical;

/**
 * Turns a terminal's spellings into a lookup from spelling to terminal.
 *
 * A lexical profile lists, for each terminal, every word that spells it. Both
 * directions are needed: realization picks a spelling for a terminal, and
 * tokenizing recognises a terminal from the spelling it met. Deriving the
 * second from the first keeps the profile a single statement of the mapping.
 *
 * @visibility root
 */
final class LexicalKeywordIndex
{
    /**
     * Inverts a terminal-to-spellings map.
     *
     * Spellings are upper-cased because SQL keywords are matched without
     * regard to case, so the index is keyed the way a tokenizer will ask.
     *
     * @param array<string, list<string>> $keywords Spellings by terminal name
     *
     * @return array<string, string> Terminal name by upper-cased spelling
     */
    public function reversed(array $keywords): array
    {
        $index = [];
        foreach ($keywords as $terminal => $spellings) {
            foreach ($spellings as $spelling) {
                $index[strtoupper($spelling)] = $terminal;
            }
        }

        return $index;
    }
    /**
     * Combines registration classes without changing spelling order or losing terminal types.
     * @param array<string, list<string>> $left
     * @param array<string, list<string>> $right
     * @return array<string, list<string>>
     */
    public function merged(array $left, array $right): array
    {
        foreach ($right as $terminal => $spellings) {
            $left[$terminal] = array_values(array_unique([...($left[$terminal] ?? []), ...$spellings]));
        }
        return $left;
    }
}
