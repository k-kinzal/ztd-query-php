<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Catalog\StatementPart;

/**
 * Lays a reconstructed statement out one clause per line.
 *
 * A statement is written in the source however the source found convenient —
 * on one long line, or across a heredoc indented to the code around it — and
 * neither reads well in a report. The formatter keeps every character that is
 * not whitespace exactly as it was, including the gaps, and rewrites only the
 * whitespace between tokens: a line break before each clause, an indent for
 * each level of nesting, and conditions under the clause they belong to.
 *
 * @visibility root
 */
final class SqlFormatter
{
    /**
     * How many columns one level of nesting is indented by.
     */
    public const STEP = 2;

    /**
     * The words a new line starts before, at the indent of the clause they open.
     */
    private const CLAUSES = ['FROM', 'WHERE', 'GROUP', 'ORDER', 'HAVING', 'LIMIT', 'OFFSET', 'UNION', 'EXCEPT', 'INTERSECT', 'RETURNING'];

    /**
     * The words that open a join phrase.
     */
    private const JOIN_OPENERS = ['LEFT', 'RIGHT', 'INNER', 'CROSS', 'FULL', 'NATURAL', 'STRAIGHT_JOIN'];

    /**
     * How the text between gaps is split into tokens.
     */
    private const TOKENS = '/(?<ws>\s+)|(?<com>--[^\n]*|\#[^\n]*|\/\*.*?\*\/)|(?<str>\'(?:\'\'|\\\\.|[^\'])*\'|"(?:""|\\\\.|[^"])*")|(?<qid>`[^`]*`)|(?<word>[A-Za-z_][A-Za-z0-9_]*)|(?<sym>.)/s';

    /**
     * The indent of the clause being written.
     */
    private int $base = 0;

    /**
     * The indent of the line being written.
     */
    private int $line = 0;

    /**
     * @var list<array{bool, int, int}>
     */
    private array $parens = [];

    private int $cases = 0;

    private bool $between = false;

    private ?int $breakAfter = null;

    private string $first = '';

    /**
     * The statement with its whitespace rewritten for reading.
     *
     * @param list<StatementPart> $parts
     * @return list<StatementPart>
     */
    public function format(array $parts): array
    {
        $tokens = $this->tokenize($parts);
        $this->base = 0;
        $this->line = 0;
        $this->parens = [];
        $this->cases = 0;
        $this->between = false;
        $this->breakAfter = null;
        $this->first = '';

        $pieces = [];
        $space = false;
        foreach ($tokens as $at => $token) {
            if ($token['kind'] === 'ws') {
                $space = true;
                continue;
            }
            $indent = $this->indentBefore($tokens, $at);
            if ($pieces !== []) {
                $pieces[] = $indent === null ? ($space ? ' ' : '') : "\n" . str_repeat(' ', $indent);
            }
            $pieces[] = $token['gap'] ?? $token['text'];
            $space = false;
        }

        return $this->assemble($pieces);
    }

    /**
     * The statement as tokens, with each gap carried as a token of its own.
     *
     * @param list<StatementPart> $parts
     * @return list<array{kind: string, text: string, gap: StatementPart|null}>
     */
    public function tokenize(array $parts): array
    {
        $tokens = [];
        foreach ($parts as $part) {
            if ($part->isGap) {
                $tokens[] = ['kind' => 'gap', 'text' => '{$}', 'gap' => $part];
                continue;
            }
            preg_match_all(self::TOKENS, $part->text, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                foreach (['ws', 'com', 'str', 'qid', 'word', 'sym'] as $kind) {
                    if (($match[$kind] ?? '') !== '') {
                        $tokens[] = ['kind' => $kind, 'text' => $match[$kind], 'gap' => null];
                        break;
                    }
                }
            }
        }

        return $tokens;
    }

    /**
     * The indent of a line starting before the token at that position, or null when no line starts there.
     *
     * @param list<array{kind: string, text: string, gap: StatementPart|null}> $tokens
     */
    public function indentBefore(array $tokens, int $at): ?int
    {
        $token = $tokens[$at];
        $indent = $this->breakAfter;
        $this->breakAfter = null;
        if ($token['kind'] === 'sym') {
            $indent = $this->symbol($token['text'], $this->wordAt($tokens, $at, 1)) ?? $indent;
        } elseif ($token['kind'] === 'word') {
            $indent = $this->word(strtoupper($token['text']), $this->wordAt($tokens, $at, -1), $this->wordAt($tokens, $at, 1)) ?? $indent;
        }
        if ($indent === null || $at === 0) {
            return null;
        }
        $this->line = $indent;

        return $indent;
    }

    /**
     * The indent of a line starting before that symbol, or null when no line starts there.
     *
     * A parenthesis that opens a subquery, or the column list of a CREATE,
     * opens a block: what is inside it is indented under the line that opened
     * it, and the closing parenthesis comes back to that line's indent.
     */
    public function symbol(string $symbol, ?string $next): ?int
    {
        if ($symbol === '(') {
            $block = $next === 'SELECT' || ($this->first === 'CREATE' && $this->parens === []);
            $this->parens[] = [$block, $this->base, $this->line];
            if ($block) {
                $this->base = $this->line + self::STEP;
                $this->breakAfter = $this->base;
            }

            return null;
        }
        if ($symbol === ')') {
            [$block, $base, $line] = array_pop($this->parens) ?? [false, $this->base, $this->line];
            $this->base = $base;

            return $block ? $line : null;
        }
        if ($symbol === ',') {
            $this->breakAfter = $this->listIndent();
        }

        return null;
    }

    /**
     * Where the next item of a list continues after a comma, or null when the list stays on its line.
     *
     * The rows of an INSERT, the assignments of an UPDATE and the columns of a
     * CREATE are each worth a line; the columns of a SELECT are not, since a
     * select list is read as one thing.
     */
    public function listIndent(): ?int
    {
        $depth = count($this->parens);
        if ($this->first === 'CREATE' && $depth === 1) {
            return $this->base;
        }
        if (($this->first === 'INSERT' || $this->first === 'REPLACE' || $this->first === 'UPDATE') && $depth === 0) {
            return $this->base + self::STEP;
        }

        return null;
    }

    /**
     * The indent of a line starting before that word, or null when no line starts there.
     */
    public function word(string $word, ?string $previous, ?string $next): ?int
    {
        if ($this->first === '') {
            $this->first = $word;
        }
        if ($word === 'BETWEEN') {
            $this->between = true;
        }
        if ($word === 'AND' || $word === 'OR') {
            $consumed = $this->between && $word === 'AND';
            $this->between = $consumed ? false : $this->between;

            return $consumed ? null : $this->base + self::STEP;
        }
        if ($word === 'CASE') {
            $this->cases++;
        }
        if ($word === 'END' && $this->cases > 0) {
            $this->cases--;

            return $this->base + $this->cases * self::STEP + self::STEP;
        }
        if (($word === 'WHEN' || $word === 'ELSE') && $this->cases > 0) {
            return $this->base + $this->cases * self::STEP + self::STEP;
        }

        return $this->clause($word, $previous, $next) ? $this->base : null;
    }

    /**
     * Whether a word opens a clause a line starts at.
     */
    public function clause(string $word, ?string $previous, ?string $next): bool
    {
        if (in_array($word, self::CLAUSES, true)) {
            return !($word === 'FROM' && $previous === 'DELETE');
        }
        if ($word === 'SET') {
            return $this->first === 'UPDATE' && $this->parens === [];
        }
        if ($word === 'VALUES') {
            return $previous === null || $previous === ')' || $previous === '{$}' || ctype_alpha($previous[0]);
        }
        if ($word === 'ON') {
            return $next === 'DUPLICATE' || $next === 'CONFLICT';
        }
        if ($word === 'SELECT') {
            return in_array($previous, ['UNION', 'ALL', 'EXCEPT', 'INTERSECT'], true);
        }
        if ($word === 'JOIN') {
            return !in_array($previous, [...self::JOIN_OPENERS, 'OUTER'], true);
        }

        return in_array($word, self::JOIN_OPENERS, true) && ($next === 'JOIN' || $next === 'OUTER');
    }

    /**
     * The nearest token in that direction that is not whitespace, as the formatter reads it.
     *
     * A word is read upper-cased, a gap as `{$}` and a symbol as itself; a
     * string, a quoted name or a comment is read as nothing, since none of
     * them opens or closes a clause.
     *
     * @param list<array{kind: string, text: string, gap: StatementPart|null}> $tokens
     * @param int $step 1 to look forward, -1 to look back
     */
    public function wordAt(array $tokens, int $at, int $step): ?string
    {
        for ($i = $at + $step, $n = count($tokens); $i >= 0 && $i < $n; $i += $step) {
            $token = $tokens[$i];
            if ($token['kind'] === 'ws') {
                continue;
            }

            return match ($token['kind']) {
                'word' => strtoupper($token['text']),
                'gap', 'sym' => $token['text'],
                default => null,
            };
        }

        return null;
    }

    /**
     * Runs of text and gaps, with adjacent runs joined.
     *
     * @param list<string|StatementPart> $pieces
     * @return list<StatementPart>
     */
    public function assemble(array $pieces): array
    {
        $parts = [];
        $text = '';
        foreach ($pieces as $piece) {
            if ($piece instanceof StatementPart) {
                if ($text !== '') {
                    $parts[] = new StatementPart($text);
                    $text = '';
                }
                $parts[] = $piece;
                continue;
            }
            $text .= $piece;
        }
        if ($text !== '') {
            $parts[] = new StatementPart($text);
        }

        return $parts;
    }
}
