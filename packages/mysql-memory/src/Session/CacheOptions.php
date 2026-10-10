<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
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
 * operand may not write are ER_WRONG_USAGE there, as `Incorrect usage of UNION and INTO`. The
 * warnings and modifiers are read by Parse\Modifiers, and the refused clauses by Parse\Clauses.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/query-cache-in-select.html.
 *
 * @visibility MySqlMemory
 */
final class CacheOptions
{
    /**
     * Records the conditions the server raises while it parses a statement it then refuses, and answers the error the statement fails with.
     *
     * A variable an INTO or a LIMIT names that no stored program declares, before where the
     * server stops, fails the statement as undeclared() tells; INTO in the query of a view fails
     * it with ER_VIEW_SELECT_VARIABLE after a variable, and ER_VIEW_SELECT_CLAUSE otherwise.
     */
    public function reported(SqlError $error, string $statement, Session $session): SqlError
    {
        $release = $session->settings()->release();
        $cause = $this->cause($error);
        if ($error->error !== StatementError::ParseError || ($release !== GrammarRelease::MySql5651 && $release !== GrammarRelease::MySql5744)) {
            return $error;
        }
        try {
            $tokens = $session->semantics()->parser()->tokenize($statement);
        } catch (LexicalException) {
            return $error;
        }
        $parsed = $cause === null || ($error->getPrevious() !== $cause && $error->getPrevious()?->getMessage() !== $cause->getMessage());
        $usage = $this->usage($error);
        [$end, $settled, $into, $union] = $this->bounds($tokens, $cause, $usage, $parsed, strlen($statement), $release);
        $variable = $this->variable($tokens, $settled < 0 && $cause !== null ? $cause->token->offset : $settled, $parsed, $usage === null || $union !== null, $session);
        if ($variable !== null) {
            return $this->undeclared(\MySqlMemory\Error\Family\ProgramError::UndeclaredVariable->error($variable), $statement, $session);
        }
        [$end, $engines, $error] = (new Parse\EnginePrefix())->conditions($tokens, $statement, $end, $session, $error);
        [$warned, $conflict] = (new Parse\Modifiers($tokens, $release))->scan($end, $settled);
        $warned += $engines;
        if ($parsed) {
            $warned += array_filter($session->dots, static fn (int $offset): bool => $offset < $end, ARRAY_FILTER_USE_KEY);
        }
        if ($into !== null) {
            $usage = (new Parse\Clauses())->marked($tokens, $into) ? \MySqlMemory\Error\Family\SchemaError::ViewSelectVariable->error() : \MySqlMemory\Error\Family\SchemaError::ViewSelectClause->error('INTO');
        }
        if ($warned === [] && $conflict === null && $usage === null) {
            return $error;
        }

        return $this->recorded($warned, $conflict, $usage ?? $error, $session, $error, false);
    }

    /**
     * Answers where MySQL 5.6 and 5.7 stop reading a statement they refuse: the offset before which they raise the warnings, the offset before which they check the modifiers of each SELECT (-1 for none), the offset of INTO in the query of a view, and the offset of the UNION after a clause a union operand may not write.
     *
     * MySQL 5.6 stops at the syntax error or that UNION, 5.7 at a syntax error it finds before the
     * end of the parse; 5.7 checks the modifiers up to the end of the nested join around a syntax
     * error found after the parse (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @param list<Token> $tokens
     * @param SyntaxException|null $cause The syntax error the statement fails with
     * @param SqlError|null $usage The ER_WRONG_USAGE error of the statement
     * @param bool $parsed Whether the statement was read to its end before it was refused
     * @param int $length The length of the statement
     * @return array{int, int, int|null, int|null}
     */
    public function bounds(array $tokens, ?SyntaxException $cause, ?SqlError $usage, bool $parsed, int $length, GrammarRelease $release): array
    {
        $clauses = new Parse\Clauses();
        $into = $usage === null ? null : $clauses->viewed($tokens);
        $union = $usage === null || !str_starts_with($usage->getMessage(), 'Incorrect usage of UNION and ') ? null : $clauses->united($tokens, str_ends_with($usage->getMessage(), 'and INTO') ? 'INTO' : 'LIMIT');
        $end = $cause !== null && (!$parsed || $release === GrammarRelease::MySql5651) ? $cause->token->offset : $length;
        $end = $into ?? ($release === GrammarRelease::MySql5651 && $union !== null ? $union : $end);
        $settled = match (true) {
            $into !== null => $into,
            $union !== null => $union,
            $cause === null => $end,
            $release === GrammarRelease::MySql5651 || $cause->token->name === 'SELECT_SYM' => $cause->token->offset,
            $parsed => $clauses->closing($tokens, $cause->token->offset),
            default => -1,
        };

        return [$end, $settled, $into, $union];
    }

    /**
     * Answers the variable MySQL 5.6 and 5.7 refuse as undeclared before they refuse a statement, or null: the first one an INTO or a LIMIT written before an offset names that no running stored program declares.
     *
     * MySQL 5.6 finds it as it parses, and 5.7 once the statement is parsed to its end; a wrong
     * usage other than a clause of a union operand comes first (verified on live 5.6.51 and
     * 5.7.44 servers).
     *
     * @param list<Token> $tokens
     * @param bool $parsed Whether the statement was read to its end before it was refused
     * @param bool $checked Whether the statement is refused for no wrong usage, or for a clause of a union operand
     */
    public function variable(array $tokens, int $end, bool $parsed, bool $checked, Session $session): ?string
    {
        if (!$checked || (!$parsed && $session->settings()->release() !== GrammarRelease::MySql5651)) {
            return null;
        }

        return (new Parse\Clauses())->unknown($tokens, $end, $session->program);
    }

    /**
     * Answers the syntax error behind an error, or null when the statement was refused for another reason.
     */
    public function cause(SqlError $error): ?SyntaxException
    {
        for ($cause = $error->getPrevious(); $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof SyntaxException) {
                return $cause;
            }
        }

        return null;
    }

    /**
     * Records the warnings MySQL 5.6 and 5.7 raise while they parse a statement they refuse, in the order of their tokens, and the error the statement fails with, which it answers: the refusal of a conflict of modifiers, or the error given.
     *
     * @param array<int, Deprecated|SqlError> $warned The warnings, by the offset of their token
     * @param array{string, string}|null $conflict The conflict of modifiers found, if any
     * @param SqlError|null $previous The error the answered error reports
     * @param bool $clear Whether the conditions recorded before are removed
     */
    public function recorded(array $warned, ?array $conflict, SqlError $error, Session $session, ?SqlError $previous = null, bool $clear = true): SqlError
    {
        ksort($warned);
        if ($clear) {
            $session->diagnostics->clear();
        }
        foreach ($warned as $construct) {
            $session->diagnostics->warning($construct instanceof SqlError ? $construct->getCode() : $construct->code(), $construct instanceof SqlError ? $construct->getMessage() : $construct->value);
        }
        $failure = $conflict === null ? $error : $this->refusal($conflict);
        $session->diagnostics->error($failure->getCode(), $failure->getMessage());

        return new SqlError($failure->error, $failure->getMessage(), $previous, [], null, null, true);
    }

    /**
     * Records the conditions MySQL 5.6 and 5.7 raise while they parse a statement up to a nested join that names a table twice, and answers the error the statement fails with: a conflict of query cache modifiers before the end of the nested join, or ER_NONUNIQ_TABLE for the alias (verified on a live 5.7.44 server).
     *
     * @param string $alias The alias named twice
     * @param int $end The offset of the end of the nested join
     */
    public function clashed(string $alias, int $end, string $statement, Session $session): SqlError
    {
        $release = $session->settings()->release();
        try {
            $tokens = $session->semantics()->parser()->tokenize($statement);
        } catch (LexicalException) {
            $tokens = [];
        }
        [$warned, $conflict] = (new Parse\Modifiers($tokens, $release))->scan($end + 1, $end + 1);
        $warned += array_filter($session->dots, static fn (int $offset): bool => $offset < $end, ARRAY_FILTER_USE_KEY);

        return $this->recorded($warned, $conflict, \MySqlMemory\Error\Family\QueryError::NonUniqueTable->error($alias), $session);
    }

    /**
     * Records the conditions MySQL 5.6 and 5.7 raise for a statement whose INTO or LIMIT names a variable no stored program declares (ER_SP_UNDECLARED_VAR), and answers the error the statement fails with.
     *
     * MySQL 5.6 finds the variable where it parses it, after the modifiers written before; 5.7
     * after the whole statement is parsed, keeping its warnings, so a conflict of query cache
     * modifiers anywhere comes first, and the other refusals of modifiers written before the
     * variable (verified on live 5.6.51 and 5.7.44 servers).
     */
    public function undeclared(SqlError $error, string $statement, Session $session): SqlError
    {
        $release = $session->settings()->release();
        try {
            $tokens = $session->semantics()->parser()->tokenize($statement);
        } catch (LexicalException) {
            return $error;
        }
        $name = substr($error->getMessage(), strlen('Undeclared variable: '));
        $at = null;
        $into = false;
        foreach ($tokens as $token) {
            $into = $into || $token->name === 'INTO' || $token->name === 'LIMIT';
            if ($into && $at === null && $token->text !== '@' && (str_starts_with($token->text, '`') ? str_replace('``', '`', substr($token->text, 1, -1)) : $token->text) === $name) {
                $at = $token->offset;
            }
        }
        if ($at === null) {
            return $error;
        }
        [$warned, $conflict] = (new Parse\Modifiers($tokens, $release))->scan($release === GrammarRelease::MySql5651 ? $at : strlen($statement), $at);

        return $this->recorded($warned, $conflict, $error, $session);
    }

    /**
     * Records the warnings MySQL 5.6 and 5.7 raise while they parse a statement up to a temporal literal they refuse, and answers the error the statement fails with: a conflict of query cache modifiers before the literal, or the refusal of the literal (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @param int $at The offset of the literal
     */
    public function literal(SqlError $error, int $at, string $statement, Session $session): SqlError
    {
        try {
            $tokens = $session->semantics()->parser()->tokenize($statement);
        } catch (LexicalException) {
            return $error;
        }
        [$warned, $conflict] = (new Parse\Modifiers($tokens, $session->settings()->release()))->scan($at, $at);

        return $this->recorded($warned, $conflict, $error, $session);
    }

    /**
     * Answers the error of a conflict of modifiers: the same modifier twice (ER_DUP_ARGUMENT), two that exclude each other (ER_WRONG_USAGE), or a modifier with no second name where it may not be written (ER_CANT_USE_OPTION_HERE).
     *
     * @param array{string, string} $conflict
     */
    public function refusal(array $conflict): SqlError
    {
        return match (true) {
            $conflict[1] === '' => StatementError::CantUseOptionHere->error($conflict[0]),
            $conflict[0] === $conflict[1] => StatementError::DuplicateArgument->error($conflict[0]),
            default => StatementError::WrongUsage->error($conflict[0], $conflict[1]),
        };
    }

    /**
     * Answers the refusal of a query cache modifier MySQL 5.6 finds before a conflict of modifiers: one outside the first query block, refused at the first modifier of its block, in a block before the one whose modifiers conflict or in that block, ALL with DISTINCT in a block before, or INTO in a union operand before (verified on a live 5.6.51 server); null otherwise.
     */
    public function placed(\SqlSemantics\Statement\Fact\Diagnostic $problem, \SqlSemantics\Statement\Node $statement, Session $session): ?SqlError
    {
        if (!$problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\CacheOptionConflict || $session->settings()->release() !== GrammarRelease::MySql5651) {
            return null;
        }
        $placement = new Placement();
        $first = $placement->first($placement->carrier($statement, GrammarRelease::MySql5651));
        $boundaries = $placement->boundaries($statement, GrammarRelease::MySql5651);
        foreach ((new \MySqlMemory\Evaluation\Compile\Walker())->find($statement, \SqlSemantics\Platform\MySql\Statement\Query\Select::class) as $select) {
            if (in_array(spl_object_id($select), $boundaries, true)) {
                return StatementError::WrongUsage->error('UNION', 'INTO');
            }
            $options = array_values(array_filter($select->options, static fn (\SqlSemantics\Platform\MySql\Statement\Query\SelectOption $option): bool => $option === \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Cache || $option === \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache));
            if ($options !== [] && $select !== $first) {
                return StatementError::CantUseOptionHere->error($options[0]->value);
            }
            if (count($options) > 1) {
                return null;
            }
            if (in_array(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::All, $select->options, true) && in_array(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct, $select->options, true)) {
                return StatementError::WrongUsage->error('ALL', 'DISTINCT');
            }
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
