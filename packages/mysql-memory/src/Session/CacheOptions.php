<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Error\StatementError;
use SqlParser\Lexer\LexicalException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\SyntaxException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

/**
 * Reports what MySQL 5.6 and 5.7 find while they parse a statement they then refuse, before the syntax error.
 *
 * Those releases raise the warnings of DELAYED, of the query cache modifiers, of PROCEDURE
 * ANALYSE and of names after a leading dot as they parse them, and check the modifiers of each
 * SELECT: 5.7 warns that SQL_CACHE and SQL_NO_CACHE are deprecated, and both refuse a modifier that meets another one,
 * as SelectOptions in SQL Semantics describes. A statement whose syntax error comes after such a
 * modifier therefore keeps the warnings, and fails with the refusal rather than the syntax error
 * when the refused modifier comes first (verified on live 5.6.51 and 5.7.44 servers). A statement
 * the grammar takes but the server refuses later is read to its end, except a SELECT among table
 * references, which 5.6 refuses before its modifiers; the clauses a union
 * operand may not write are ER_WRONG_USAGE there, as `Incorrect usage of UNION and INTO`.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/query-cache-in-select.html.
 *
 * @visibility MySqlMemory
 */
final class CacheOptions
{
    /**
     * The tokens of the modifiers a SELECT writes before its select list.
     */
    public const MODIFIERS = ['ALL', 'DISTINCT', 'HIGH_PRIORITY', 'STRAIGHT_JOIN', 'SQL_SMALL_RESULT', 'SQL_BIG_RESULT', 'SQL_BUFFER_RESULT', 'SQL_CALC_FOUND_ROWS', 'SQL_CACHE_SYM', 'SQL_NO_CACHE_SYM'];

    /**
     * Records the conditions the server raises while it parses a statement it then refuses, and answers the error the statement fails with.
     */
    public function reported(SqlError $error, string $statement, Session $session): SqlError
    {
        $release = $session->settings()->release();
        $cause = $error->getPrevious();
        while ($cause !== null && !$cause instanceof SyntaxException) {
            $cause = $cause->getPrevious();
        }
        if ($error->error !== StatementError::ParseError || ($release !== GrammarRelease::MySql5651 && $release !== GrammarRelease::MySql5744)) {
            return $error;
        }
        $parsed = !$cause instanceof SyntaxException || $error->getPrevious() !== $cause;
        $end = $cause instanceof SyntaxException && (!$parsed || $release === GrammarRelease::MySql5651) ? $cause->token->offset : strlen($statement);
        try {
            $tokens = $session->semantics()->parser()->tokenize($statement);
        } catch (LexicalException) {
            return $error;
        }
        [$warned, $conflict] = $this->notices($tokens, $end, $release);
        if ($parsed) {
            $warned += array_filter($session->dots, static fn (int $offset): bool => $offset < $end, ARRAY_FILTER_USE_KEY);
        }
        ksort($warned);
        $usage = $this->usage($error);
        if ($warned === [] && $conflict === null && $usage === null) {
            return $error;
        }
        foreach ($warned as $construct) {
            $session->diagnostics->warning($construct->code(), $construct->value);
        }
        $failure = $usage ?? $error;
        if ($conflict !== null) {
            $failure = $conflict[0] === $conflict[1] ? StatementError::DuplicateArgument->error($conflict[0]) : StatementError::WrongUsage->error($conflict[0], $conflict[1]);
        }
        $session->diagnostics->error($failure->getCode(), $failure->getMessage());

        return new SqlError($failure->error, $failure->getMessage(), $error, [], null, null, true);
    }

    /**
     * Answers the warnings the release raises for the tokens before an offset, by the offset of their token, and the first conflict of query cache modifiers, which ends the parse.
     *
     * The warnings are those of DELAYED, of the query cache modifiers and of PROCEDURE ANALYSE.
     *
     * @param list<Token> $tokens
     * @return array{array<int, Deprecated>, array{string, string}|null}
     */
    public function notices(array $tokens, int $end, GrammarRelease $release): array
    {
        $warned = [];
        $conflict = null;
        $listing = false;
        [$first, $previous, $before] = [null, null, ''];
        foreach ($tokens as $token) {
            if ($token->offset >= $end || $conflict !== null) {
                break;
            }
            $construct = $this->deprecated($token->name, $before, $release);
            $before = $token->name;
            if ($token->name === 'SELECT_SYM') {
                [$listing, $first, $previous] = [true, null, null];

                continue;
            }
            $listing = $listing && in_array($token->name, self::MODIFIERS, true);
            $cache = $listing && in_array($token->name, ['SQL_CACHE_SYM', 'SQL_NO_CACHE_SYM'], true) ? ($token->name === 'SQL_CACHE_SYM' ? 'SQL_CACHE' : 'SQL_NO_CACHE') : null;
            if ($cache !== null) {
                $construct = $cache === 'SQL_CACHE' ? Deprecated::Cache : Deprecated::NoCache;
            }
            if ($construct !== null && $construct->warnedIn($release)) {
                $warned[$token->offset] = $construct;
            }
            $earlier = $release === GrammarRelease::MySql5651 ? $first : $previous;
            $conflict = $cache !== null && $earlier !== null ? [$earlier, $cache] : null;
            $previous = $cache;
            $first ??= $cache;
        }

        return [$warned, $conflict];
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

    /**
     * Answers the refusal of a query cache modifier MySQL 5.6 finds before a conflict of modifiers: one outside the first query block, refused at the first modifier of its block (verified on a live 5.6.51 server); null otherwise.
     */
    public function placed(\SqlSemantics\Statement\Fact\Diagnostic $problem, \SqlSemantics\Statement\Node $statement, Session $session): ?SqlError
    {
        if (!$problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\CacheOptionConflict || $session->settings()->release() !== GrammarRelease::MySql5651) {
            return null;
        }
        try {
            (new Placement())->cached($statement, GrammarRelease::MySql5651);
        } catch (SqlError $refusal) {
            return $refusal;
        }

        return null;
    }

    /**
     * Answers the ER_WRONG_USAGE error of a statement SQL Semantics refuses in the words of the server, as `Incorrect usage of UNION and INTO: ...`, or null.
     */
    public function usage(SqlError $error): ?SqlError
    {
        for ($cause = $error->getPrevious(); $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof AnalysisException && preg_match('/\AIncorrect usage of ([^:,]+?) and ([^:,]+?)(?::|\z)/', $cause->getMessage(), $match) === 1) {
                return StatementError::WrongUsage->error($match[1], $match[2]);
            }
        }

        return null;
    }
}
