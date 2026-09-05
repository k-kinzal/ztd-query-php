<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

/**
 * Applies what PostgreSQL's parser enforces outside its grammar.
 *
 * A Bison grammar only says which token sequences the parser will shift and
 * reduce; the C actions attached to those rules reject a good deal more. A
 * derivation that satisfied only the grammar would write SQL the parser
 * refuses, so every such constraint is applied here before the terminals are
 * handed to the lexer.
 *
 * @visibility root
 */
final class ParserSemantics
{
    /**
     * @param LexicalGrammar $lexicalGrammar Answers how a lookahead terminal is finally spelled
     */
    public function __construct(private readonly LexicalGrammar $lexicalGrammar)
    {
    }

    /**
     * Applies the constraints PostgreSQL enforces in semantic actions rather than in its grammar.
     *
     * The grammar accepts SET (name) and OPERATOR (op) with shapes the parser
     * then rejects in C, and it accepts qualified names of any depth where the
     * parser accepts at most three parts. A derivation that only satisfied the
     * grammar would therefore write SQL PostgreSQL refuses to parse.
     *
     * @param list<string> $terminals Terminals a derivation produced
     *
     * @return list<string> Terminals with every semantic constraint satisfied
     */
    public function applied(array $terminals): array
    {
        $terminals = $this->truncateQualifiedNames($terminals);

        foreach ($terminals as $index => $terminal) {
            if ($terminal !== 'SET' || ($terminals[$index + 1] ?? null) !== '(') {
                continue;
            }
            $end = $this->matchingParen($terminals, $index + 1);
            if ($end === null) {
                continue;
            }
            $depth = 1;
            for ($cursor = $index + 2; $cursor < $end; $cursor++) {
                if ($terminals[$cursor] === '(') {
                    $depth++;
                } elseif ($terminals[$cursor] === ')') {
                    $depth--;
                }
                if ($depth === 1
                    && $this->isIdentifierTerminal($terminals[$cursor])
                    && in_array($terminals[$cursor + 1] ?? null, [',', ')'], true)
                    && ($terminals[$cursor - 1] ?? null) !== '='
                ) {
                    array_splice($terminals, $cursor + 1, 0, ['=', 'NONE']);
                    $end += 2;
                    $cursor += 2;
                }
            }
        }

        foreach ($terminals as $index => $terminal) {
            if ($terminal !== 'OPERATOR' || ($terminals[$index + 1] ?? null) === '(') {
                continue;
            }
            $start = array_search('(', array_slice($terminals, $index + 1), true);
            if ($start === false) {
                continue;
            }
            $start += $index + 1;
            $end = $this->matchingParen($terminals, $start);
            if ($end !== null && $end - $start === 2 && $terminals[$start + 1] !== ',') {
                array_splice($terminals, $start + 1, 0, ['NONE', ',']);
            }
        }

        return $this->lexicalGrammar->normalizeLookahead($terminals);
    }

    /**
     * Cuts a qualified name down to the depth the parser accepts.
     *
     * @param list<string> $terminals Terminals a derivation produced
     *
     * @return list<string> Terminals whose qualified names are at most three parts deep
     */
    public function truncateQualifiedNames(array $terminals): array
    {
        $result = [];
        $count = count($terminals);
        for ($index = 0; $index < $count; $index++) {
            $terminal = $terminals[$index];
            if (!$this->isIdentifierTerminal($terminal) || ($terminals[$index + 1] ?? null) !== '.') {
                $result[] = $terminal;
                continue;
            }

            $chain = [$terminal];
            while ($index + 2 < $count && $terminals[$index + 1] === '.') {
                $following = $terminals[$index + 2];
                if ($following === '*') {
                    $index += 2;
                    break;
                }
                if (!$this->isIdentifierTerminal($following)) {
                    break;
                }
                $chain[] = '.';
                $chain[] = $following;
                $index += 2;
            }
            array_push($result, ...array_slice($chain, 0, 5));
        }

        return $result;
    }

    /**
     * Answers where the parenthesis opened at one position is closed.
     *
     * @param list<string> $terminals Terminals a derivation produced
     * @param int $open Position of the opening parenthesis
     *
     * @return int|null Position of the matching close, or null when there is none
     */
    public function matchingParen(array $terminals, int $open): ?int
    {
        $depth = 0;
        for ($index = $open; $index < count($terminals); $index++) {
            if ($terminals[$index] === '(') {
                $depth++;
            } elseif ($terminals[$index] === ')' && --$depth === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Reports whether a terminal stands for a name rather than a keyword.
     *
     * @param string $terminal Terminal to judge
     *
     * @return bool True when the terminal is an identifier
     */
    public function isIdentifierTerminal(string $terminal): bool
    {
        return in_array($terminal, ['IDENT', 'UIDENT'], true)
            || preg_match('/^[a-z_][a-z0-9_]*$/', $terminal) === 1;
    }
}
