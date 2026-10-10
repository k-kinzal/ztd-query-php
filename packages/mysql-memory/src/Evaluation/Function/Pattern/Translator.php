<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Pattern;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;

/**
 * Reads a regular expression as ICU does, and writes the same expression for PCRE.
 *
 * MySQL 8.0 and later match regular expressions with ICU. The expression is read character by
 * character with the ICU syntax: literals and the escapes \a \e \f \n \r \t \cX \xhh \x{h..}
 * \uhhhh \Uhhhhhhhh \0ooo \N{name}; an escaped character that is no escape stands for itself;
 * the classes ., \d \D \s \S \w \W \h \H \v \V \R \X, \p{..} \P{..} and sets in brackets with
 * ranges, nested sets, [:property:], && (intersection) and -- (difference); the anchors ^ $ \A
 * \z \Z \G \b \B; groups (..), (?:..), (?>..), (?<name>..), lookahead and lookbehind, (?#..)
 * comments and flags (?dimsuwx-dimsuwx) and (?dimsuwx-dimsuwx:..); back references \n and
 * \k<name>; \Q..\E; greedy, lazy and possessive quantifiers * + ? {n} {n,} {n,m}. The anchors,
 * the dot and the classes are written out with the ICU line terminators and character classes,
 * so that PCRE matches what ICU matches.
 *
 * The errors are ICU's, in the order ICU finds them: an empty pattern is ER_REGEXP_ILLEGAL_ARGUMENT;
 * a syntax error names the line and character it stands at (ER_REGEXP_RULE_SYNTAX); an unclosed
 * parenthesis or bracket, a bad interval or escape, a range out of order, an unknown property
 * or flag, an unbounded lookbehind, a group number or name that no group has, and a number
 * above 16777215 each have their own error. A back reference is checked once the pattern is
 * read, so a reference to a later group is valid. A pattern PCRE cannot compile, such as one with
 * a repetition count in the thousands, fails with ER_REGEXP_PATTERN_TOO_BIG, where the server
 * would match it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/regexp.html#regexp-syntax,
 * https://unicode-org.github.io/icu/userguide/strings/regexp.html; the order of the errors and
 * the positions were verified on a live 8.4 server.
 *
 * @visibility MySqlMemory\Evaluation\Function\Pattern
 */
final class Translator
{
    /**
     * The expressions translated so far, or the error and message each gave, by mode and pattern.
     *
     * An error is kept as its code and message, not as the exception, whose trace would hold on to
     * every object of the statement that first read the pattern.
     *
     * @var array<string, Expression|array{ErrorCode, string}>
     */
    public static array $translated = [];

    /**
     * The characters whose full case folding is more than one character, by their folding.
     *
     * @var array<string, list<string>>|null
     */
    public static ?array $expansions = null;

    /**
     * The reader of the pattern being translated.
     */
    public Scanner $scanner;

    /**
     * The mode at the point being read.
     */
    public Mode $mode;

    /**
     * The capture groups opened so far.
     */
    public int $groups = 0;

    /**
     * The number of each named group, by name.
     *
     * @var array<string, int>
     */
    public array $names = [];

    /**
     * The back references, in order: the digits or the name each writes, and whether it is a name.
     *
     * @var list<array{string, bool}>
     */
    public array $references = [];

    /**
     * Whether the pattern finds grapheme clusters or Unicode word boundaries, for which ICU reads the rules of a locale.
     */
    public bool $located = false;

    /**
     * Writes the dot and the anchors.
     */
    public readonly Anchors $anchors;

    /**
     * Reads the quantifiers.
     */
    public readonly Quantifiers $quantifiers;

    /**
     * Reads the groups.
     */
    public readonly Grouping $grouping;

    /**
     * Reads the escapes.
     */
    public readonly Escapes $escapes;

    /**
     * Reads the sets in brackets.
     */
    public readonly Brackets $brackets;

    /**
     * @param Properties $properties Answers the sets that properties and escapes name
     */
    public function __construct(public readonly Properties $properties = new Properties())
    {
        $this->scanner = Scanner::of('');
        $this->mode = new Mode();
        $this->anchors = new Anchors($this);
        $this->quantifiers = new Quantifiers($this);
        $this->grouping = new Grouping($this);
        $this->escapes = new Escapes($this);
        $this->brackets = new Brackets($this);
    }

    /**
     * Translates a pattern in a mode, once for each pattern and mode.
     *
     * @throws SqlError When the pattern is not valid
     */
    public static function compile(string $pattern, Mode $mode): Expression
    {
        $key = $mode->key() . $pattern;
        if (!isset(self::$translated[$key])) {
            try {
                self::$translated[$key] = (new self())->translate($pattern, $mode);
            } catch (SqlError $error) {
                self::$translated[$key] = [$error->error, $error->getMessage()];
            }
        }
        $translated = self::$translated[$key];
        if (is_array($translated)) {
            throw new SqlError($translated[0], $translated[1]);
        }

        return $translated;
    }

    /**
     * Translates a UTF-8 pattern in a mode.
     *
     * @throws SqlError When the pattern is not valid
     */
    public function translate(string $pattern, Mode $mode): Expression
    {
        if ($pattern === '') {
            throw DataError::RegexpError->error();
        }
        $this->scanner = Scanner::of($pattern);
        $this->mode = $mode;
        $this->groups = 0;
        $this->names = [];
        $this->references = [];
        $this->located = false;
        $body = $this->alternation();
        if (!$this->scanner->done()) {
            $this->scanner->next();
            throw DataError::RegexpMismatchedParenthesis->error();
        }
        $source = '/' . ($mode->caseless ? '(?i)' : '') . $this->resolve($body->source) . '/u';
        set_error_handler(static fn (): bool => true);
        try {
            $compiled = preg_match($source, '') !== false;
        } finally {
            restore_error_handler();
        }
        if (!$compiled) {
            throw DataError::RegexpPatternTooBig->error();
        }

        return new Expression($source, $this->groups, $this->names, $this->located);
    }

    /**
     * Reads alternatives up to the end of the pattern or of the group.
     *
     * @throws SqlError When the pattern is not valid
     */
    public function alternation(): Fragment
    {
        $branches = [$this->sequence()];
        while ($this->scanner->peek() === '|') {
            $this->scanner->next();
            $branches[] = $this->sequence();
        }

        return Fragment::choice($branches);
    }

    /**
     * Reads the terms of one alternative.
     *
     * @throws SqlError When the pattern is not valid
     */
    public function sequence(): Fragment
    {
        $parts = [];
        while (true) {
            $this->skip();
            $character = $this->scanner->peek();
            if ($character === null || $character === '|' || $character === ')') {
                break;
            }
            $atoms = $this->atom();
            $last = array_pop($atoms);
            array_push($parts, ...$atoms);
            if ($last !== null) {
                $parts[] = $this->quantifiers->quantified($last);
            }
        }

        return Fragment::sequence($this->folded($parts));
    }

    /**
     * Answers a literal character; without regard to case, one whose full case folding is several characters also matches them.
     */
    public function literal(string $character): Fragment
    {
        if (!$this->mode->caseless) {
            return new Fragment(Members::escape($character));
        }
        $folded = mb_convert_case($character, MB_CASE_FOLD, 'UTF-8');
        $length = mb_strlen($folded, 'UTF-8');
        $source = $length > 1 ? '(?:' . Members::escape($character) . '|' . implode('', array_map(Members::escape(...), mb_str_split($folded, 1, 'UTF-8'))) . ')' : Members::escape($character);

        return new Fragment($source, 1, max(1, $length), true, $folded);
    }

    /**
     * Answers the terms of a sequence where literal characters matched without regard to case
     * that spell the full case folding of a character also match that character, as ICU folds
     * strings: SS matches ß.
     *
     * @param list<Fragment> $parts
     * @return list<Fragment>
     */
    public function folded(array $parts): array
    {
        $result = [];
        for ($index = 0, $count = count($parts); $index < $count; $index++) {
            $run = '';
            $replaced = false;
            for ($end = $index; $end < min($count, $index + 3) && $parts[$end]->folded !== null && $index < $count - 1; $end++) {
                $run .= $parts[$end]->folded;
                $characters = $end > $index ? self::expansions()[$run] ?? [] : [];
                if ($characters !== []) {
                    $span = array_slice($parts, $index, $end - $index + 1);
                    $sequence = Fragment::sequence($span);
                    $result[] = new Fragment('(?:' . $sequence->source . '|' . implode('|', array_map(Members::escape(...), $characters)) . ')', 1, $sequence->maximum);
                    $index = $end;
                    $replaced = true;
                    break;
                }
            }
            if (!$replaced) {
                $result[] = $parts[$index];
            }
        }

        return $result;
    }

    /**
     * Answers the characters whose full case folding is more than one character, by their folding, listed once.
     *
     * @return array<string, list<string>>
     */
    public static function expansions(): array
    {
        if (self::$expansions === null) {
            self::$expansions = [];
            for ($code = 0x80; $code <= 0x1FFFF; $code++) {
                if ($code >= 0xD800 && $code <= 0xDFFF) {
                    continue;
                }
                $character = mb_chr($code, 'UTF-8');
                $folded = mb_convert_case($character, MB_CASE_FOLD, 'UTF-8');
                if (mb_strlen($folded, 'UTF-8') > 1) {
                    self::$expansions[$folded][] = $character;
                }
            }
        }

        return self::$expansions;
    }

    /**
     * Skips white space and # comments in the x mode.
     */
    public function skip(): void
    {
        while ($this->mode->comments) {
            $character = $this->scanner->peek();
            if ($character !== null && $this->space($character)) {
                $this->scanner->next();
            } elseif ($character === '#') {
                while (!in_array($this->scanner->next(), [null, "\n", "\r", "\u{85}", "\u{2028}", "\u{2029}"], true)) {
                }
            } else {
                return;
            }
        }
    }

    /**
     * Tells whether a character is pattern white space.
     */
    public function space(string $character): bool
    {
        return in_array($character, ["\t", "\n", "\x0B", "\f", "\r", ' ', "\u{85}", "\u{200E}", "\u{200F}", "\u{2028}", "\u{2029}"], true);
    }

    /**
     * Reads one term without its quantifier; \Q..\E gives one term for each character.
     *
     * @return list<Fragment>
     *
     * @throws SqlError When the pattern is not valid
     */
    public function atom(): array
    {
        $character = (string) $this->scanner->next();

        return match ($character) {
            '(' => $this->grouping->group(),
            '[' => [new Fragment($this->brackets->set()->source())],
            '.' => [new Fragment($this->anchors->dot())],
            '^' => [Fragment::empty('(?:' . $this->anchors->caret() . ')', true)],
            '$' => [Fragment::empty('(?:' . $this->anchors->dollar() . ')', true)],
            '\\' => $this->escapes->escape(),
            '*', '+', '?', '{', '}' => throw $this->scanner->syntax(),
            default => [$this->literal($character)],
        };
    }

    /**
     * Writes the back references of a translated source, now that every group is known.
     *
     * A reference may name a group that follows it.
     * @throws SqlError When a reference names no group
     */
    public function resolve(string $source): string
    {
        $pieces = explode("\0", $source);
        $resolved = '';
        foreach ($pieces as $index => $piece) {
            if ($index % 2 === 0) {
                $resolved .= $piece;
                continue;
            }
            [$text, $named] = $this->references[(int) $piece];
            if ($named) {
                if (!isset($this->names[$text])) {
                    throw DataError::RegexpInvalidCaptureGroupName->error();
                }
                $resolved .= '(?:\g{' . $this->names[$text] . '})';
                continue;
            }
            if ((int) $text > $this->groups) {
                throw DataError::RegexpInvalidBackReference->error();
            }
            $resolved .= '(?:\g{' . $text . '})';
        }

        return $resolved;
    }
}
