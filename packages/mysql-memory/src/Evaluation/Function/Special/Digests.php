<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Special;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Session\Syntax;
use MySqlMemory\Value\Encoding;
use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The statement digest functions of MySQL 8.0 and later: STATEMENT_DIGEST and STATEMENT_DIGEST_TEXT.
 *
 * A statement is read with the grammar of the release and the session's sql_mode, and its
 * tokens are stored as the server stores them, two bytes each in little-endian order: a keyword
 * or operator by its number in the grammar (the terminal number plus 256 below terminal 304,
 * plus 257 up to YYUNDEF and plus 407 after it), a single character by its code, an identifier
 * as 1106 followed by the length and the bytes of its name. A number, a string or a NULL literal
 * is a value (1100), and the server reduces as it stores: a sign before a number after an
 * operator other than a comparison, `?, ?` to a list (1101), `(?)` and `(...)` to rows (1102 and
 * 1104), rows separated by commas to `(?) /* , ... *\/` and `(...) /* , ... *\/` (1103, 1105), and
 * `IN (...)` to one token (1110). At most 1024 bytes are kept. STATEMENT_DIGEST is the SHA-256 of
 * the bytes, STATEMENT_DIGEST_TEXT the tokens written back with spaces between them, a keyword by
 * the last of its spellings. An optimizer hint comment right after SELECT, INSERT, REPLACE, UPDATE
 * or DELETE is stored between 1108 and 1109, its keywords by their numbers from 1000 (verified on
 * live 8.0.44, 8.4 and 9.1.0 servers). A statement the server cannot read fails with error
 * 3676 quoting the problem: a syntax error, an empty text, or a table named without its database
 * while no database is chosen.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/encryption-functions.html#function_statement-digest,
 * https://dev.mysql.com/doc/refman/8.4/en/performance-schema-statement-digests.html.
 *
 * @visibility MySqlMemory
 */
final class Digests
{
    /**
     * The value token.
     */
    public const VALUE = 1100;

    /**
     * The token of a list of values.
     */
    public const VALUES = 1101;

    /**
     * The token of an identifier.
     */
    public const IDENTIFIER = 1106;

    /**
     * The most bytes a digest keeps.
     */
    public const LENGTH = 1024;

    /**
     * The literal tokens that are values.
     */
    public const LITERALS = ['NUM', 'LONG_NUM', 'ULONGLONG_NUM', 'DECIMAL_NUM', 'FLOAT_NUM', 'HEX_NUM', 'BIN_NUM', 'TEXT_STRING', 'NCHAR_STRING', 'NULL_SYM', 'DOLLAR_QUOTED_STRING_SYM', 'LEX_HOSTNAME'];

    /**
     * The literal tokens of numbers, before which a sign folds into the value.
     */
    public const NUMBERS = ['NUM', 'LONG_NUM', 'ULONGLONG_NUM', 'DECIMAL_NUM', 'FLOAT_NUM', 'HEX_NUM', 'BIN_NUM'];

    /**
     * The tokens after which a sign before a number folds into it.
     */
    public const FOLDING = ['SELECT_SYM', 'DEFAULT_SYM', 'LIKE', 'BETWEEN_SYM', 'AND_SYM', 'OR_SYM', 'XOR', 'DIV_SYM', 'MOD_SYM', 'INTERVAL_SYM', 'SHIFT_LEFT', 'SHIFT_RIGHT', 'OR2_SYM', 'AND_AND_SYM', 'REGEXP', '(', ',', '-', '+', '*', '/', '%', '|', '&', '^'];

    /**
     * The texts of the tokens that are no keyword.
     */
    public const TEXTS = ['UNDERSCORE_CHARSET' => '(_charset)', 'JSON_SEPARATOR_SYM' => '->', 'JSON_UNQUOTED_SEPARATOR_SYM' => '->>', 'OR2_SYM' => '||', 'SET_VAR' => ':=', 'PARAM_MARKER' => '?', 'WITH_ROLLUP_SYM' => 'WITH ROLLUP', 'NE' => '!='];

    /**
     * The keywords of optimizer hints, by their number.
     */
    public const HINTS = [
        'MAX_EXECUTION_TIME' => 1000, 'RESOURCE_GROUP' => 1001, 'BKA' => 1002, 'BNL' => 1003, 'DUPSWEEDOUT' => 1004, 'FIRSTMATCH' => 1005,
        'INTOEXISTS' => 1006, 'LOOSESCAN' => 1007, 'MATERIALIZATION' => 1008, 'NO_BKA' => 1009, 'NO_BNL' => 1010, 'NO_ICP' => 1011,
        'NO_MRR' => 1012, 'NO_RANGE_OPTIMIZATION' => 1013, 'NO_SEMIJOIN' => 1014, 'MRR' => 1015, 'QB_NAME' => 1016, 'SEMIJOIN' => 1017,
        'SUBQUERY' => 1018, 'MERGE' => 1019, 'NO_MERGE' => 1020, 'JOIN_PREFIX' => 1021, 'JOIN_SUFFIX' => 1022, 'JOIN_ORDER' => 1023,
        'JOIN_FIXED_ORDER' => 1024, 'INDEX_MERGE' => 1025, 'NO_INDEX_MERGE' => 1026, 'SET_VAR' => 1027, 'SKIP_SCAN' => 1028,
        'NO_SKIP_SCAN' => 1029, 'HASH_JOIN' => 1030, 'NO_HASH_JOIN' => 1031, 'INDEX' => 1039, 'NO_INDEX' => 1040, 'JOIN_INDEX' => 1041,
        'NO_JOIN_INDEX' => 1042, 'GROUP_INDEX' => 1043, 'NO_GROUP_INDEX' => 1044, 'ORDER_INDEX' => 1045, 'NO_ORDER_INDEX' => 1046,
        'DERIVED_CONDITION_PUSHDOWN' => 1047, 'NO_DERIVED_CONDITION_PUSHDOWN' => 1048,
    ];

    /**
     * The keywords an optimizer hint comment may follow.
     */
    public const HINTED = ['SELECT_SYM', 'INSERT_SYM', 'REPLACE_SYM', 'UPDATE_SYM', 'DELETE_SYM'];

    /**
     * The character sets a digest cannot read.
     */
    public const WIDE = ['ucs2', 'utf16', 'utf16le', 'utf32'];

    /**
     * The spelling of each keyword token, by release.
     *
     * @var array<string, array<string, string>>
     */
    public static array $spellings = [];

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('STATEMENT_DIGEST', 1, 1, fn (Frame $f, array $a): ?string => ($tokens = $this->tokens($f, $a[0], 'statement_digest')) === null ? null : hash('sha256', $this->bytes($tokens[0])[0])),
            new Routine('STATEMENT_DIGEST_TEXT', 1, 1, fn (Frame $f, array $a): ?string => ($tokens = $this->tokens($f, $a[0], 'statement_digest_text')) === null ? null : Encoding::convert($this->bytes($tokens[0])[1], Charset::known('utf8mb4'), $tokens[1])),
        ];
    }

    /**
     * Reads the statement an argument writes into its digest tokens, or answers null for NULL.
     *
     * @return array{list<array{int, string, string, string}>, Charset}|null The tokens: number, bytes after the number, text and terminal name; and the character set of the argument
     *
     * @throws SqlError When the argument is in a wide character set, or no statement
     */
    public function tokens(Frame $frame, Evaluable $argument, string $function): ?array
    {
        $domain = $argument->domain();
        $text = Convert::toText($argument->evaluate($frame), $domain);
        if ($text === null) {
            return null;
        }
        $charset = $domain->kind === Kind::String ? $domain->collation->charset : Charset::known('utf8mb4');
        if (in_array($charset->name, self::WIDE, true)) {
            throw DataError::UnsupportedFunctionCharset->error($function, $domain->collation->name);
        }
        $statement = Encoding::convert($text, $charset->name === 'binary' ? Charset::known('utf8mb4') : $charset, Charset::known('utf8mb4'));
        $tree = $this->parse($frame, $statement);

        return [$this->digest($tree, $frame->context->modes->release->value, $charset), $charset->name === 'binary' ? Charset::known('utf8mb4') : $charset];
    }

    /**
     * Parses a statement as the server does for a digest: with the grammar of the release, refusing what the parser of the server refuses.
     *
     * @throws SqlError When the text is no statement
     */
    public function parse(Frame $frame, string $statement): Node
    {
        if (trim($statement) === '') {
            throw StatementError::DigestParseFailure->error(StatementError::EmptyQuery->message());
        }
        $context = $frame->context;
        $semantics = new Semantics(Dialect::MySql, $context->modes->release->value, Mode::fromString((string) $context->variables->read('sql_mode')), ParameterStyle::Native);
        $syntax = new Syntax();
        try {
            $tree = $semantics->parser()->parse($statement);
            $syntax->markers($tree, $statement);
            $syntax->temporals($tree, $context->modes);
        } catch (SourceException $error) {
            throw StatementError::DigestParseFailure->error($syntax->error($error, $statement)->getMessage());
        } catch (SqlError $error) {
            throw StatementError::DigestParseFailure->error($error->getMessage());
        }
        if ($context->variables->database === '' && $this->unqualified($tree)) {
            throw StatementError::DigestParseFailure->error(QueryError::NoDatabase->message());
        }

        return $tree;
    }

    /**
     * Tells whether a statement names a table without its database, which the server resolves while it parses.
     */
    public function unqualified(Node $tree): bool
    {
        $common = [];
        foreach ($tree->find('common_table_expr') as $expression) {
            $common[] = strtolower(trim($expression->tokens()[0]->text ?? '', '`'));
        }
        foreach ($tree->find('table_ident') as $name) {
            $tokens = $name->tokens();
            if (count($tokens) === 1 && !in_array(strtolower(trim($tokens[0]->text, '`')), $common, true)) {
                return true;
            }
        }
        foreach ($tree->find('grant_ident') as $name) {
            if (count($name->tokens()) === 1 && $name->tokens()[0]->text !== '*') {
                return true;
            }
        }
        foreach ($tree->find('show_tables_stmt') as $show) {
            if (!in_array('FROM', array_map(static fn (Token $token): string => $token->name, $show->tokens()), true) && !in_array('IN_SYM', array_map(static fn (Token $token): string => $token->name, $show->tokens()), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stores the tokens of a parsed statement as the server stores them for its digest.
     *
     * @return list<array{int, string, string, string}>
     */
    public function digest(Node $tree, string $release, Charset $charset): array
    {
        $spellings = self::$spellings[$release] ??= $this->spellings($release);
        $tokens = [];
        $this->collect($tree, $tokens);
        $stored = [];
        $previous = '';
        foreach ($tokens as [$token, $parent]) {
            $name = $token->name;
            if (in_array($previous, self::HINTED, true) && preg_match('/\A\s*\/\*\+(.*?)\*\//s', $token->leading, $hint) === 1) {
                $this->hint($stored, $hint[1], $charset);
            }
            $previous = $name;
            if (in_array($name, self::LITERALS, true) && ($name !== 'NULL_SYM' || $parent === 'null_as_literal')) {
                $this->value($stored, in_array($name, self::NUMBERS, true));
                continue;
            }
            if ($name === 'IDENT' || $name === 'IDENT_QUOTED') {
                $identifier = $name === 'IDENT_QUOTED' && str_starts_with($token->text, '`') ? str_replace('``', '`', substr($token->text, 1, -1)) : $token->text;
                $bytes = Encoding::convert($identifier, Charset::known('utf8mb4'), $charset->name === 'binary' ? Charset::known('utf8mb4') : $charset);
                $stored[] = [self::IDENTIFIER, pack('v', strlen($bytes)) . $bytes, '`' . $identifier . '`', $name];
                continue;
            }
            $text = strlen($name) === 1 ? $name : (self::TEXTS[$name] ?? $spellings[$name] ?? $name);
            $this->add($stored, strlen($name) === 1 ? ord($name) : self::number($token->symbol), $text, $name);
        }

        return $stored;
    }

    /**
     * Stores the tokens of an optimizer hint comment between its delimiters (1108 and 1109).
     *
     * @param list<array{int, string, string, string}> $stored
     */
    public function hint(array &$stored, string $body, Charset $charset): void
    {
        preg_match_all('/\s*(?:(`(?:[^`]|``)*`)|(\'[^\']*\'|"[^"]*")|([0-9]+(?:\.[0-9]+)?[KMGkmg]?)|([A-Za-z_$][A-Za-z0-9_$]*)|(\S))(@?)/', $body, $words, PREG_SET_ORDER);
        if ($words === []) {
            return;
        }
        $stored[] = [1108, '', '/*+', ''];
        foreach ($words as $word) {
            $quoted = $word[1];
            $name = $word[4];
            if ($word[2] !== '' || $word[3] !== '') {
                $this->value($stored, false);
            } elseif ($quoted !== '' || ($name !== '' && !isset(self::HINTS[strtoupper($name)]))) {
                $identifier = $quoted !== '' ? str_replace('``', '`', substr($quoted, 1, -1)) : $name;
                $bytes = Encoding::convert($identifier, Charset::known('utf8mb4'), $charset->name === 'binary' ? Charset::known('utf8mb4') : $charset);
                $stored[] = [$word[6] === '@' ? 1107 : self::IDENTIFIER, pack('v', strlen($bytes)) . $bytes, '`' . $identifier . '`', $word[6] === '@' ? 'IDENT_AT' : 'IDENT'];
            } elseif ($name !== '') {
                $stored[] = [self::HINTS[strtoupper($name)], '', strtoupper($name), ''];
            } else {
                $this->add($stored, ord($word[5]), $word[5], $word[5]);
            }
            if ($word[6] === '@') {
                $stored[] = [64, '', '@', '@'];
            }
        }
        $stored[] = [1109, '', '*/', ''];
    }

    /**
     * Collects the tokens of a tree in text order, each with the rule that holds it.
     *
     * @param list<array{Token, string}> $tokens
     */
    public function collect(Node $node, array &$tokens): void
    {
        foreach ($node->children as $child) {
            if ($child instanceof Token) {
                if ($child->name !== 'END_OF_INPUT' && $child->name !== '$end') {
                    $tokens[] = [$child, $node->name];
                }
                continue;
            }
            $this->collect($child, $tokens);
        }
    }

    /**
     * Answers the number the server gives a terminal of the grammar.
     */
    public static function number(int $terminal): int
    {
        return $terminal + ($terminal < 304 ? 256 : ($terminal < 743 ? 257 : 407));
    }

    /**
     * Answers the spelling of each keyword token of a release: the last of the keywords and the functions that spell it.
     *
     * @return array<string, string>
     */
    public function spellings(string $release): array
    {
        $path = \SqlParser\MySql\MySqlVersion::resolve($release)->release->keywordPath;
        $table = is_file($path) ? require $path : [];
        $spellings = [];
        foreach (is_array($table) ? [$table['keywords'] ?? [], $table['functions'] ?? []] : [] as $group) {
            foreach (is_array($group) ? $group : [] as $text => $token) {
                if (is_string($token)) {
                    $spellings[$token] = (string) $text;
                }
            }
        }

        return $spellings;
    }

    /**
     * Stores a value: a sign before a number folds into it, and values separated by commas into a list.
     *
     * @param list<array{int, string, string, string}> $stored
     */
    public function value(array &$stored, bool $number): void
    {
        while ($number && count($stored) >= 2 && in_array($stored[count($stored) - 1][3], ['-', '+'], true) && in_array($stored[count($stored) - 2][3], self::FOLDING, true)) {
            array_pop($stored);
        }
        $top = count($stored);
        if ($top >= 2 && $stored[$top - 1][0] === 44 && in_array($stored[$top - 2][0], [self::VALUE, self::VALUES], true)) {
            array_splice($stored, $top - 2);
            $stored[] = [self::VALUES, '', '?, ...', ''];

            return;
        }
        $stored[] = [self::VALUE, '', '?', ''];
    }

    /**
     * Stores a token; a closing parenthesis reduces values into rows and `IN (...)`.
     *
     * @param list<array{int, string, string, string}> $stored
     */
    public function add(array &$stored, int $number, string $text, string $name): void
    {
        $top = count($stored);
        if ($number !== 41 || $top < 2 || $stored[$top - 2][0] !== 40 || !in_array($stored[$top - 1][0], [self::VALUE, self::VALUES], true)) {
            $stored[] = [$number, '', $text, $name];

            return;
        }
        $single = $stored[$top - 1][0] === self::VALUE;
        array_splice($stored, $top - 2);
        $top = count($stored);
        if ($top >= 2 && $stored[$top - 1][0] === 44 && in_array($stored[$top - 2][0], $single ? [1102, 1103] : [1104, 1105], true)) {
            array_splice($stored, $top - 2);
            $stored[] = $single ? [1103, '', '(?) /* , ... */', ''] : [1105, '', '(...) /* , ... */', ''];

            return;
        }
        if ($top >= 1 && $stored[$top - 1][3] === 'IN_SYM') {
            array_splice($stored, $top - 1);
            $stored[] = [1110, '', 'IN (...)', ''];

            return;
        }
        $stored[] = $single ? [1102, '', '(?)', ''] : [1104, '', '(...)', ''];
    }

    /**
     * Answers the bytes and the text of the stored tokens, at most 1024 bytes of them.
     *
     * @param list<array{int, string, string, string}> $stored
     * @return array{string, string}
     */
    public function bytes(array $stored): array
    {
        $bytes = '';
        $text = '';
        $glue = '';
        foreach ($stored as [$number, $after, $written]) {
            $chunk = pack('v', $number) . $after;
            if (strlen($bytes) + strlen($chunk) > self::LENGTH) {
                break;
            }
            $bytes .= $chunk;
            $text .= $glue . $written;
            $glue = $written === '@' || $number === 1107 ? '' : ' ';
        }

        return [$bytes, $text];
    }
}
