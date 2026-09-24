<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Catalog\StatementPart;

/**
 * Renders a reconstructed statement as marked-up SQL.
 *
 * The statement is rendered from the pattern rather than from the text it
 * displays as, so a gap keeps what is known about it. A gap is a claim about
 * the analysis and not about the statement, and rendering it as an ordinary
 * `{$}` in the middle of the SQL hides which of the two a reader is looking at.
 * Here it is marked, tinted by where the value came from, and says so when
 * pointed at. The tokens are written in the classes doc-ui highlights.
 *
 * @visibility root
 */
final class SqlHighlighter
{
    /**
     * The words written as keywords.
     */
    private const KEYWORDS = 'SELECT|INSERT|UPDATE|DELETE|REPLACE|MERGE|TRUNCATE|CREATE|ALTER|DROP|RENAME|CALL|SHOW|EXPLAIN|DESCRIBE|WITH|RECURSIVE|FROM|WHERE|GROUP|BY|HAVING|ORDER|LIMIT|OFFSET|FETCH|JOIN|INNER|LEFT|RIGHT|FULL|OUTER|CROSS|NATURAL|LATERAL|ON|USING|UNION|INTERSECT|EXCEPT|ALL|DISTINCT|AS|INTO|VALUES|SET|DEFAULT|RETURNING|AND|OR|NOT|IN|IS|NULL|LIKE|ILIKE|RLIKE|REGEXP|BETWEEN|EXISTS|CASE|WHEN|THEN|ELSE|END|ASC|DESC|NULLS|FIRST|LAST|TABLE|VIEW|INDEX|DATABASE|SCHEMA|COLUMN|CONSTRAINT|PRIMARY|FOREIGN|KEY|UNIQUE|REFERENCES|CASCADE|RESTRICT|IF|DUPLICATE|IGNORE|STRAIGHT_JOIN|SQL_CALC_FOUND_ROWS|FOR|SHARE|LOCK|BEGIN|START|TRANSACTION|COMMIT|ROLLBACK|SAVEPOINT|GRANT|REVOKE|ANALYZE|OPTIMIZE|VACUUM|PRAGMA|CAST|CONVERT|COLLATE|INTERVAL|PARTITION|OVER|WINDOW|FILTER|GROUPS|RANGE|ROWS|UNBOUNDED|PRECEDING|FOLLOWING|CURRENT|ROW|TRUE|FALSE|UNSIGNED|BINARY|CHARACTER|CHARSET|ADD|MODIFY|CHANGE|AUTO_INCREMENT|ENGINE|STATUS|TEMPORARY|EXISTS';

    /**
     * How the tokens of a resolved run are told apart.
     */
    public const TOKENS = '/(?<com>--[^\n]*|\#[^\n]*|\/\*.*?\*\/)|(?<str>\'(?:\'\'|\\\\.|[^\'])*\'|"(?:""|\\\\.|[^"])*")|(?<qid>`[^`]*`)|(?<ph>\?|:[A-Za-z_][A-Za-z0-9_]*|\$[0-9]+|%[sdfF]\b)|(?<num>\b[0-9]+(?:\.[0-9]+)?\b)|(?<word>[A-Za-z_][A-Za-z0-9_]*)/s';

    /**
     * The class each kind of token is written with, as doc-ui names them.
     */
    private const TOKEN_CLASSES = ['com' => 'tok-com', 'str' => 'tok-str', 'qid' => 'tok-id', 'ph' => 'tok-var', 'num' => 'tok-num'];

    private HtmlText $text;

    /**
     * Wires the highlighter to the escaping it writes through.
     */
    public function __construct(?HtmlText $text = null)
    {
        $this->text = $text ?? new HtmlText();
    }

    /**
     * The whole statement, with its known runs highlighted and its gaps marked.
     *
     * @param list<StatementPart> $parts
     */
    public function render(array $parts): string
    {
        $rendered = '';
        foreach ($parts as $part) {
            $rendered .= $part->isGap ? $this->hole($part) : $this->highlight($part->text);
        }

        return $rendered === '' ? '<span class="none">(empty)</span>' : $rendered;
    }

    /**
     * The displayed SQL without markup, for navigation and search labels.
     *
     * @param list<StatementPart> $parts
     */
    public function plain(array $parts): string
    {
        return implode('', array_map(fn (StatementPart $part): string => $part->isGap ? $this->holeLabel($part) : $part->text, $parts));
    }

    /**
     * The whole statement on one line, for a listing.
     *
     * @param list<StatementPart> $parts
     */
    public function inline(array $parts): string
    {
        $collapsed = [];
        foreach ($parts as $part) {
            $collapsed[] = $part->isGap ? $part : new StatementPart((string) preg_replace('/\s+/', ' ', $part->text));
        }

        return $this->render($collapsed);
    }

    /**
     * One resolved run of SQL, with its tokens marked.
     *
     * The run is tokenized before it is escaped, not after. Escaping first
     * turns an apostrophe into an entity, and marking a token inside an entity
     * breaks it, so what a reader sees is the escaping rather than the SQL.
     */
    public function highlight(string $sql): string
    {
        if (preg_match_all(self::TOKENS, $sql, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            return $this->text->escape($sql);
        }

        $marked = '';
        $at = 0;
        foreach ($matches as $match) {
            [$whole, $offset] = $match[0];
            $marked .= $this->text->escape(substr($sql, $at, $offset - $at)) . $this->token($this->captured($match));
            $at = $offset + strlen($whole);
        }

        return $marked . $this->text->escape(substr($sql, $at));
    }

    /**
     * The named captures of a match, without the offsets they were captured with.
     *
     * @param array<array-key, array{string, int}> $match
     * @return array<string, string>
     */
    public function captured(array $match): array
    {
        $captured = [];
        foreach ($match as $name => $capture) {
            if (is_string($name)) {
                $captured[$name] = $capture[0];
            }
        }

        return $captured;
    }

    /**
     * One matched token, wrapped in the class its kind is written with.
     *
     * @param array<string, string> $match
     */
    public function token(array $match): string
    {
        foreach (['com', 'str', 'qid', 'ph', 'num'] as $kind) {
            if (($match[$kind] ?? '') !== '') {
                return '<span class="' . self::TOKEN_CLASSES[$kind] . '">'
                    . $this->text->escape($match[$kind]) . '</span>';
            }
        }
        $word = $match['word'] ?? '';

        return $this->isKeyword($word)
            ? '<span class="tok-kw">' . $this->text->escape($word) . '</span>'
            : $this->text->escape($word);
    }

    /**
     * Whether a word is one SQL writes as a keyword.
     */
    public function isKeyword(string $word): bool
    {
        return in_array(strtoupper($word), explode('|', self::KEYWORDS), true);
    }

    /**
     * One gap, marked with where the value that fills it comes from.
     */
    public function hole(StatementPart $gap): string
    {
        $note = 'This is a gap: ' . $gap->reason . ' fills it.';
        if ($gap->expression !== null) {
            $note .= ' Written as ' . $gap->expression . '.';
        }

        return '<span class="hole ' . $this->text->escape($this->holeRole($gap->origin)) . '" title="'
            . $this->text->escape($note) . '">' . $this->text->escape($this->holeLabel($gap)) . '</span>';
    }

    /**
     * A named PHP variable when known, or the anonymous gap marker.
     */
    public function holeLabel(StatementPart $gap): string
    {
        $variable = $gap->variable ?? $gap->expression;

        return $variable !== null && preg_match('/\A\$[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*\z/', $variable) === 1
            ? '{' . $variable . '}'
            : '{$}';
    }

    /**
     * The tone a gap of that origin is tinted by.
     *
     * A value from outside the program is the one thing in a statement worth
     * a warning colour; a call the analysis never reached is not a claim about
     * the statement at all and is written in no colour; every other gap is a
     * caution.
     */
    public function holeRole(string $origin): string
    {
        return match ($origin) {
            'external' => 'tone-danger',
            'unreached' => 'tone-neutral',
            default => 'tone-warn',
        };
    }
}
