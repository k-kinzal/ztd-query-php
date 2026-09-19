<?php

declare(strict_types=1);

namespace SqlParser\PostgreSql\Lexer;

use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;

/**
 * The token substitutions PostgreSQL makes between its scanner and its parser.
 *
 * A few keywords mean something else when a particular keyword follows:
 * `NOT` before `LIKE` is the `NOT` of the predicate, `WITH` before `TIME`
 * belongs to a type name. The parser's own lexer wrapper looks one token
 * ahead and renames them, and it also finishes Unicode literals, which may
 * carry their escape character in a following `UESCAPE` clause.
 *
 * @visibility root
 */
final class LookaheadFilter
{
    /**
     * Renamed terminal by the terminal that precedes a listed one.
     */
    public const SUBSTITUTIONS = [
        'FORMAT' => ['FORMAT_LA', ['JSON']],
        'NOT' => ['NOT_LA', ['BETWEEN', 'IN_P', 'LIKE', 'ILIKE', 'SIMILAR']],
        'NULLS_P' => ['NULLS_LA', ['FIRST_P', 'LAST_P']],
        'WITH' => ['WITH_LA', ['TIME', 'ORDINALITY']],
        'WITHOUT' => ['WITHOUT_LA', ['TIME']],
    ];

    /**
     * Applies the substitutions to a lexeme sequence.
     *
     * @param list<Lexeme> $lexemes Lexemes as scanned
     * @param string $source The SQL text
     *
     * @return list<Lexeme> Lexemes as the parser receives them
     *
     * @throws LexicalException When a `UESCAPE` clause is malformed
     */
    public function apply(array $lexemes, string $source): array
    {
        $filtered = [];
        for ($index = 0, $count = count($lexemes); $index < $count; $index++) {
            $lexeme = $lexemes[$index];
            $following = $lexemes[$index + 1] ?? null;
            $substitution = self::SUBSTITUTIONS[$lexeme->name] ?? null;
            if ($substitution !== null && $following !== null && in_array($following->name, $substitution[1], true)) {
                $filtered[] = new Lexeme($substitution[0], $lexeme->text, $lexeme->offset);
            } elseif ($lexeme->name === 'UIDENT' || $lexeme->name === 'USCONST') {
                $filtered[] = $this->unicode($lexeme, $lexemes, $index, $source);
            } else {
                $filtered[] = $lexeme;
            }
        }

        return $filtered;
    }

    /**
     * Finishes a Unicode literal, taking in its `UESCAPE` clause when one follows.
     *
     * @param Lexeme $lexeme The Unicode identifier or string
     * @param list<Lexeme> $lexemes Every lexeme, to look ahead in
     * @param int $index Position of the literal, advanced past the clause
     * @param string $source The SQL text
     *
     * @return Lexeme The plain identifier or string
     *
     * @throws LexicalException When `UESCAPE` is not followed by a one-character string
     */
    public function unicode(Lexeme $lexeme, array $lexemes, int &$index, string $source): Lexeme
    {
        $name = $lexeme->name === 'UIDENT' ? 'IDENT' : 'SCONST';
        if (($lexemes[$index + 1] ?? null)?->name !== 'UESCAPE') {
            return new Lexeme($name, $lexeme->text, $lexeme->offset);
        }
        $escape = $lexemes[$index + 2] ?? null;
        if ($escape === null || $escape->name !== 'SCONST' || strlen($escape->text) !== 3 || preg_match('/^\'[^0-9A-Fa-f+\'"\s]\'$/', $escape->text) !== 1) {
            throw LexicalException::unexpectedCharacter($source, $lexemes[$index + 1]->offset);
        }
        $index += 2;

        return new Lexeme($name, substr($source, $lexeme->offset, $escape->end() - $lexeme->offset), $lexeme->offset);
    }
}
