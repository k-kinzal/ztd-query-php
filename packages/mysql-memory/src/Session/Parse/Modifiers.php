<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use SqlParser\Lexer\Token;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

/**
 * Reads the tokens of a statement as MySQL 5.6 and 5.7 parse them, for the warnings they raise and the first conflict of the modifiers of a SELECT they find.
 *
 * The warnings are those of DELAYED, of the query cache modifiers and of PROCEDURE ANALYSE: 5.7
 * warns that SQL_CACHE and SQL_NO_CACHE are deprecated. A conflict of query cache modifiers ends
 * the parse. ALL and DISTINCT in the modifiers of one SELECT conflict where the modifiers end:
 * MySQL 5.6 finds it as it parses, except in a union operand of a derived table, whose modifiers
 * it reads by another rule, and 5.7 after the parse, so a conflict of query cache modifiers
 * anywhere comes first (verified on live 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/5.7/en/query-cache-in-select.html.
 *
 * @visibility MySqlMemory
 */
final class Modifiers
{
    /**
     * The tokens of the modifiers a SELECT writes before its select list.
     */
    public const MODIFIERS = ['ALL', 'DISTINCT', 'HIGH_PRIORITY', 'STRAIGHT_JOIN', 'SQL_SMALL_RESULT', 'SQL_BIG_RESULT', 'SQL_BUFFER_RESULT', 'SQL_CALC_FOUND_ROWS', 'SQL_CACHE_SYM', 'SQL_NO_CACHE_SYM'];

    /**
     * @var array<int, Deprecated> The warnings raised so far, by the offset of their token
     */
    public array $warned = [];

    /**
     * @var array{string, string}|null The conflict of query cache modifiers found, which ends the parse
     */
    public ?array $conflict = null;

    /**
     * @var array{string, string}|null The first conflict found where the modifiers of a SELECT end
     */
    public ?array $exclusive = null;

    /**
     * @var bool Whether the token read last is a SELECT or one of its modifiers
     */
    public bool $listing = false;

    /**
     * @var string|null The first query cache modifier of the SELECT being read
     */
    public ?string $first = null;

    /**
     * @var string|null The query cache modifier the token read last writes
     */
    public ?string $previous = null;

    /**
     * @var string The name of the token read last
     */
    public string $before = '';

    /**
     * @var int The depth of parentheses
     */
    public int $depth = 0;

    /**
     * @var int|null The depth of parentheses of the last UNION
     */
    public ?int $union = null;

    /**
     * @var bool Whether the tokens read since the last UNION may still start its operand
     */
    public bool $operand = false;

    /**
     * @var bool Whether the release checks ALL and DISTINCT in the SELECT being read
     */
    public bool $checked = true;

    /**
     * @var array<string, true> The token names of the modifiers of the SELECT being read, the last written last
     */
    public array $written = [];

    /**
     * @var bool Whether the SELECT being read starts a subquery
     */
    public bool $nested = false;

    /**
     * @var array<int, bool> Whether the clause at each depth of parentheses is FROM
     */
    public array $clauses = [];

    /**
     * @var int The number of query blocks read that are no subquery
     */
    public int $blocks = 0;

    /**
     * @param list<Token> $tokens The tokens of the statement
     * @param GrammarRelease $release The release that parses the statement
     */
    public function __construct(public readonly array $tokens, public readonly GrammarRelease $release)
    {
    }

    /**
     * Answers the warnings the release raises for the tokens before an offset, by the offset of their token, and the first conflict of modifiers; ALL and DISTINCT are checked where the modifiers end before $settled.
     *
     * @param int $settled The offset before which ALL and DISTINCT are checked
     * @return array{array<int, Deprecated>, array{string, string}|null}
     */
    public function scan(int $end, int $settled = PHP_INT_MAX): array
    {
        foreach ($this->tokens as $index => $token) {
            if ($this->stopped()) {
                break;
            }
            $modifier = $this->listing && in_array($token->name, self::MODIFIERS, true) && !($this->nested && in_array($token->name, ['SQL_CACHE_SYM', 'SQL_NO_CACHE_SYM'], true));
            if ($this->listing && !$modifier && $token->offset <= $settled) {
                $this->exclusive ??= $this->ended($this->written, $this->checked, $this->release === GrammarRelease::MySql5744 && $this->blocks > 1);
            }
            if ($token->offset >= $end || $this->stopped()) {
                break;
            }
            $this->read($index, $token, $modifier);
        }

        return [$this->warned, $this->conflict ?? $this->exclusive];
    }

    /**
     * Tells whether the parse has ended: at a conflict of query cache modifiers, or in MySQL 5.6 at a conflict where the modifiers of a SELECT end.
     */
    public function stopped(): bool
    {
        return $this->conflict !== null || ($this->exclusive !== null && $this->release === GrammarRelease::MySql5651);
    }

    /**
     * Reads one token: the parentheses, unions and clauses around it, then a SELECT, or a token after one.
     *
     * @param int $index The index of the token
     * @param bool $modifier Whether the token is a modifier of the SELECT before it
     */
    public function read(int $index, Token $token, bool $modifier): void
    {
        $construct = $this->deprecated($token->name, $this->before, $this->release);
        $this->before = $token->name;
        $this->depth += match ($token->text) {
            '(' => 1,
            ')' => -1,
            default => 0,
        };
        $after = $this->operand;
        $this->operand = $token->name === 'UNION_SYM' || ($this->operand && in_array($token->text, ['(', 'ALL', 'DISTINCT'], true));
        $this->union = $token->name === 'UNION_SYM' ? $this->depth : $this->union;
        if (in_array($token->name, ['FROM', 'WHERE', 'GROUP_SYM', 'HAVING', 'ORDER_SYM', 'LIMIT', 'SELECT_SYM'], true)) {
            $this->clauses[$this->depth] = $token->name === 'FROM';
        }
        if ($token->name === 'SELECT_SYM') {
            $this->select($index, $after);

            return;
        }
        $this->option($token, $modifier, $construct);
    }

    /**
     * Starts a SELECT: MySQL 5.6 checks ALL and DISTINCT in a union operand only at the top level, and the query cache modifiers of a subquery are read as names.
     *
     * @param int $index The index of the SELECT
     * @param bool $after Whether the SELECT may start the operand of a UNION
     */
    public function select(int $index, bool $after): void
    {
        $this->checked = $this->release !== GrammarRelease::MySql5651 || !$after || $this->union === null || $this->union === 0;
        $this->nested = $this->subquery($this->tokens, $index, $this->clauses, $this->depth);
        $this->blocks += $this->nested ? 0 : 1;
        [$this->listing, $this->first, $this->previous, $this->written, $this->operand] = [true, null, null, [], false];
    }

    /**
     * Reads a token that is not a SELECT: a modifier is written, the warning of its deprecated construct is raised, and a query cache modifier conflicts with the first (MySQL 5.6) or the last (5.7) one before it in the SELECT, or in MySQL 5.6 with being outside the first query block.
     *
     * @param bool $modifier Whether the token is a modifier of the SELECT before it
     * @param Deprecated|null $construct The deprecated construct the token starts
     */
    public function option(Token $token, bool $modifier, ?Deprecated $construct): void
    {
        if ($modifier) {
            unset($this->written[$token->name]);
            $this->written[$token->name] = true;
        }
        $this->listing = $modifier;
        $cache = $modifier && in_array($token->name, ['SQL_CACHE_SYM', 'SQL_NO_CACHE_SYM'], true) ? ($token->name === 'SQL_CACHE_SYM' ? 'SQL_CACHE' : 'SQL_NO_CACHE') : null;
        if ($cache !== null) {
            $construct = $cache === 'SQL_CACHE' ? Deprecated::Cache : Deprecated::NoCache;
        }
        if ($construct !== null && $construct->warnedIn($this->release)) {
            $this->warned[$token->offset] = $construct;
        }
        $earlier = $this->release === GrammarRelease::MySql5651 ? $this->first : $this->previous;
        $this->conflict = match (true) {
            $cache === null => null,
            $this->release === GrammarRelease::MySql5651 && $this->blocks > 1 => [$cache, ''],
            default => $earlier === null ? null : [$earlier, $cache],
        };
        $this->previous = $cache;
        $this->first ??= $cache;
    }

    /**
     * Answers the conflict MySQL 5.7 finds where the modifiers of a SELECT end, or MySQL 5.6 ALL with DISTINCT: a query cache modifier where the block may not write one, when one is the last modifier or ALL and DISTINCT do not meet, else ALL with DISTINCT; or null (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @param array<string, true> $written The token names of the modifiers of the block, the last written last
     * @param bool $checked Whether the release checks ALL and DISTINCT in the block
     * @param bool $misplaced Whether a query cache modifier is refused in the block
     * @return array{string, string}|null
     */
    public function ended(array $written, bool $checked, bool $misplaced): ?array
    {
        $caches = array_values(array_intersect(array_keys($written), ['SQL_CACHE_SYM', 'SQL_NO_CACHE_SYM']));
        $placement = $misplaced && $caches !== [] ? [$caches[count($caches) - 1] === 'SQL_CACHE_SYM' ? 'SQL_CACHE' : 'SQL_NO_CACHE', ''] : null;
        $last = array_key_last($written);
        $exclusive = $checked && isset($written['ALL'], $written['DISTINCT']) ? ['ALL', 'DISTINCT'] : null;

        return in_array($last, ['SQL_CACHE_SYM', 'SQL_NO_CACHE_SYM'], true) ? $placement ?? $exclusive : $exclusive ?? $placement;
    }

    /**
     * Answers whether the SELECT at an index of the tokens starts a subquery, whose query cache modifiers MySQL 5.6 and 5.7 read as names: in parentheses after anything but the start of the statement, FROM, a join, a comma among table references, AS, UNION, a table name or the column list of an INSERT.
     *
     * @param list<Token> $tokens
     * @param array<int, bool> $clauses Whether the clause at each depth of parentheses is FROM
     * @param int $depth The depth of parentheses at the SELECT
     */
    public function subquery(array $tokens, int $index, array $clauses, int $depth): bool
    {
        $opened = 0;
        while (($tokens[$index - $opened - 1]->text ?? null) === '(') {
            $opened++;
        }
        $before = $tokens[$index - $opened - 1] ?? null;
        if ($opened === 0 || $before === null) {
            return false;
        }

        return !(in_array($before->name, ['FROM', 'JOIN_SYM', 'STRAIGHT_JOIN', 'AS', 'UNION_SYM'], true) || $before->text === ')'
            || (in_array($before->text, ['ALL', 'DISTINCT'], true) && ($tokens[$index - $opened - 2]->name ?? null) === 'UNION_SYM')
            || ($before->text === ',' && ($clauses[$depth - $opened] ?? false))
            || ($opened === 1 && $before->name === 'IDENT'));
    }

    /**
     * Answers the deprecated construct a token starts after the token before it: DELAYED after
     * INSERT or REPLACE, in the words of the release, and ANALYSE after PROCEDURE; null otherwise.
     */
    public function deprecated(string $name, string $before, GrammarRelease $release): ?Deprecated
    {
        return match (true) {
            $name === 'DELAYED_SYM' && $before === 'INSERT' => $release === GrammarRelease::MySql5651 ? Deprecated::DelayedInsert : Deprecated::InsertDelayed,
            $name === 'DELAYED_SYM' && $before === 'REPLACE' => $release === GrammarRelease::MySql5651 ? Deprecated::DelayedReplace : Deprecated::ReplaceDelayed,
            $name === 'ANALYSE_SYM' && $before === 'PROCEDURE_SYM' => Deprecated::ProcedureAnalyse,
            default => null,
        };
    }
}
