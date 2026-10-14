<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\CacheOptions;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\ReductionParser;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseStop;

/**
 * Preserves modern operator and SELECT-modifier warnings before a syntax refusal.
 *
 * A candidate must have been accepted at its original position by SQL Parser.
 * The versioned warning descriptors belong to SQL Semantics. No fragment is
 * executed. Observed on native MySQL 8.0.44, 8.4.7 and 9.1.0 connections.
 *
 * @visibility MySqlMemory
 */
final class ModernWarnings
{
    /**
     * Records notices that precede the rejected token, including unprepared markers.
     */
    public function reported(SqlError $error, string $statement, Session $session): SqlError
    {
        if ($error->recorded || $error->error !== StatementError::ParseError || $session->settings()->legacy()) {
            return $error;
        }
        $condition = (new CacheOptions())->condition($error, $statement, $session);
        if ($condition !== null) {
            return $condition;
        }
        $parser = $session->semantics()->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        $end = (new CacheOptions())->cause($error)?->token->offset ?? strlen($statement);
        try {
            $tokens = $parser->tokenize($statement);
        } catch (SourceException $failure) {
            $end = min($end, $failure->offset);
            $tokens = $parser->tokenize(substr($statement, 0, $end));
            $statement = substr($statement, 0, $end);
        }
        if (strtoupper($tokens[0]->text ?? '') === 'ALTER') {
            $error = (new CommandPrefix())->before($tokens, $statement, $end, $session) ?? $error;
            $end = (new CacheOptions())->cause($error)?->token->offset ?? $end;
        }
        $notices = (new SyntaxWarnings())->completed($parser, $statement, $end, $session->semantics()->profile());
        foreach ($this->before($tokens, $statement, $end, $parser) as $offset => $notice) {
            $notices[] = [$offset, $notice];
        }
        usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $warnings = array_column($notices, 1);

        return $warnings === [] ? $error : $this->recorded($warnings, $error, $session);
    }

    /**
     * Records typed conditions in order, retaining the first error as the reply.
     *
     * @param list<Deprecated|\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation|\SqlSemantics\Platform\MySql\Statement\Notice\NameConversion|ParseFailure|ParseStop> $notices The completed semantic notices
     * @param bool $append Whether the final refusal follows the recorded notices or is already among them
     */
    public function recorded(array $notices, SqlError $syntax, Session $session, bool $append = true): SqlError
    {
        $session->diagnostics->clear();
        $first = null;
        $aborted = false;
        foreach ($notices as $notice) {
            if ($notice instanceof ParseStop) {
                $aborted = true;
                break;
            }
            if ($notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation) {
                $session->diagnostics->warning($notice->code(), $notice->message());
                if ($notice->aborts) {
                    $aborted = true;
                    break;
                }
                continue;
            }
            if ($notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\NameConversion) {
                $session->diagnostics->warning($notice->code(), $notice->message());
                continue;
            }
            if ($notice instanceof Deprecated) {
                $session->diagnostics->warning($notice->code(), $notice->value);
                continue;
            }
            $failure = (new Errors())->error($notice->problem, $session);
            $session->diagnostics->error($failure->getCode(), $failure->getMessage());
            $first ??= $failure;
            if ($notice->aborts) {
                $aborted = true;
                break;
            }
        }
        if (!$aborted && $append) {
            $session->diagnostics->error($syntax->getCode(), $syntax->getMessage());
        }
        $first ??= $syntax;

        return new SqlError($first->error, $first->getMessage(), $syntax, [], null, null, true);
    }

    /**
     * Finds accepted deprecated tokens in source order, before the refusal.
     *
     * Contiguous exclamation marks warn once per pair, with one further notice
     * for an odd final mark; separated marks each warn. Quoted text is never
     * interpreted as an operator or a SELECT modifier.
     *
     * @param list<Token> $tokens The statement tokens
     * @return array<int, Deprecated> Notices indexed by their input offsets
     */
    public function before(array $tokens, string $statement, int $end, SqlParser $parser): array
    {
        $warnings = [];
        $modifiers = false;
        $bangs = 0;
        foreach ($tokens as $index => $token) {
            if ($token->offset >= $end) {
                break;
            }
            $modifiers = $token->name === 'SELECT_SYM' || ($modifiers && in_array($token->name, Modifiers::MODIFIERS, true));
            $previous = $tokens[$index - 1] ?? null;
            $bangs = $token->name === '!' ? ($previous?->end() === $token->offset ? $bangs + 1 : 1) : 0;
            $notice = match ($token->name) {
                '!' => $bangs % 2 === 1 ? Deprecated::BangNot : null,
                'OR2_SYM' => Deprecated::PipesOr,
                'AND_AND_SYM' => Deprecated::AmpersandsAnd,
                'SQL_NO_CACHE_SYM' => $modifiers ? Deprecated::NoCache : null,
                'SQL_CALC_FOUND_ROWS' => $modifiers ? Deprecated::CalcFoundRows : null,
                default => null,
            };
            if ($notice !== null && $this->accepted($parser, $statement, $token)) {
                $warnings[$token->offset] = $notice;
            }
        }

        return $warnings;
    }

    /**
     * Confirms that the token is reached before a grammar error in its prefix.
     */
    public function accepted(SqlParser $parser, string $statement, Token $token): bool
    {
        try {
            $parser->parse(substr($statement, 0, $token->end()));
        } catch (SourceException $failure) {
            return $failure->offset >= $token->end();
        }

        return true;
    }
}
