<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Value\Temporal;
use SqlParser\Lexer\LexicalException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlParser\Parser\SyntaxException;
use Throwable;

/**
 * Reports text the grammar refuses as the server does: ER_PARSE_ERROR near the text that follows the refused token.
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_parse_error.
 *
 * @visibility MySqlMemory
 */
final class Syntax
{
    /**
     * The deprecated constructs written before the names of a statement, whose warnings come before those of leading dots.
     */
    public const HEAD = [
        \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::InsertDelayed, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::ReplaceDelayed,
        \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::Cache, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::NoCache,
        \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::CalcFoundRows,
    ];

    /**
     * Answers the deprecations MySQL 5.7 raises for names written after a leading dot, `.t` and `.t.c`, by the offset of the dot (verified on a live 5.7.44 server).
     *
     * @return array<int, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated>
     */
    public function dots(Node $tree): array
    {
        $found = [];
        foreach (['table_ident' => \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::DotTable, 'simple_ident_q' => \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::DotColumn] as $rule => $construct) {
            foreach ($tree->find($rule) as $node) {
                $first = $node->children[0] ?? null;
                if ($first instanceof Token && $first->text === '.') {
                    $found[$first->offset] = $construct;
                }
            }
        }
        ksort($found);

        return $found;
    }

    /**
     * Refuses PARSE_GCOL_EXPR, which the 5.7 grammar holds for the server to read generated columns with, as a syntax error at its start when a client sends it, and a partitioning clause sent alone, which the 5.6 and 5.7 grammars hold for the server to read the partitioning of a table with, as a syntax error that tells so (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @throws SqlError When the statement is PARSE_GCOL_EXPR or a partitioning clause
     */
    public function internal(\SqlSemantics\Statement\Statement $statement, string $text): void
    {
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\ParseGeneratedColumn) {
            throw new SqlError(StatementError::ParseError, StatementError::ParseError->message(mb_strcut(ltrim($text), 0, 80, 'UTF-8'), 1));
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Partition\PartitionEntry) {
            throw new SqlError(StatementError::ParseError, "Partitioning can not be used stand-alone in query near '" . mb_strcut(ltrim($text), 0, 80, 'UTF-8') . "' at line 1");
        }
    }

    /**
     * Refuses a subquery in the moment of PURGE BINARY LOGS BEFORE, which MySQL 5.6 and 5.7 take no subquery in, as a syntax error: 5.7 near the text right after the parenthesis that opens the first subquery, 5.6 near its SELECT, or near the parenthesis after EXISTS, ALL, ANY or SOME (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @throws SqlError When the moment holds a subquery
     */
    public function purged(Node $tree, \SqlSemantics\Statement\Statement $statement, string $text, \SqlSemantics\Contract\GrammarRelease $release): void
    {
        $legacy = $release === \SqlSemantics\Contract\GrammarRelease::MySql5651 || $release === \SqlSemantics\Contract\GrammarRelease::MySql5744;
        $subquery = $tree->find('subselect')[0] ?? null;
        $first = $subquery?->tokens()[0] ?? null;
        if (!$legacy || !$statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore || $first === null) {
            return;
        }
        $parenthesis = (int) strrpos(substr($text, 0, $first->offset), '(');
        $before = rtrim(substr($text, 0, $parenthesis));
        $at = match (true) {
            $release === \SqlSemantics\Contract\GrammarRelease::MySql5744 => $parenthesis + 1,
            preg_match('/\b(EXISTS|ALL|ANY|SOME)\z/i', $before) === 1 => $parenthesis,
            default => $first->offset,
        };

        throw new SqlError(StatementError::ParseError, StatementError::ParseError->message(mb_strcut(substr($text, $at), 0, 80, 'UTF-8'), substr_count(substr($text, 0, $at), "\n") + 1));
    }

    /**
     * Refuses a parameter marker outside a prepared statement, as the parser of the server does.
     *
     * @param string $following The statements written after this one in the same text, which the error quotes too
     * @throws SqlError When the statement holds a parameter marker
     */
    public function markers(Node $tree, string $statement, string $following = ''): void
    {
        foreach ($tree->tokens() as $token) {
            if ($token->text === '?' && $token->name === 'PARAM_MARKER') {
                throw $this->near($token->offset, $statement, $following, new SyntaxException($token, [], ''));
            }
        }
    }

    /**
     * Answers the parse error at a token: it quotes the text from the token, the statements written after it included, up to 80 bytes, and names its line, counted from the first word of the statement (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers).
     *
     * @param string $following The statements written after this one in the same text
     */
    public function near(int $offset, string $statement, string $following, Throwable $failure): SqlError
    {
        $line = substr_count(ltrim(substr($statement, 0, $offset)), "\n") + 1;

        return new SqlError(StatementError::ParseError, StatementError::ParseError->message(mb_strcut(substr($statement, $offset) . $following, 0, 80, 'UTF-8'), $line), $failure);
    }

    /**
     * Answers the refusal of a parameter marker outside a prepared statement that the server meets before the syntax error a failure reports, or null (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers).
     *
     * @param string $following The statements written after this one in the same text
     */
    public function premature(Throwable $failure, string $statement, \SqlParser\Parser\SqlParser $parser, string $following = ''): ?SqlError
    {
        $cause = $failure;
        while ($cause !== null && !$cause instanceof SyntaxException) {
            $cause = $cause->getPrevious();
        }
        try {
            $tokens = $parser->tokenize($statement);
        } catch (LexicalException) {
            return null;
        }
        foreach ($tokens as $token) {
            if ($cause instanceof SyntaxException && $token->offset >= $cause->token->offset) {
                return null;
            }
            if ($token->name === 'PARAM_MARKER') {
                return $this->near($token->offset, $statement, $following, new SyntaxException($token, [], ''));
            }
        }

        return null;
    }

    /**
     * Refuses SHOW PARSE_TREE, which only a debug build of the server runs, as the parser of a release build does: a syntax error at PARSE_TREE.
     *
     * The server reads the whole statement first, so the problems it finds while it parses the
     * statement inside come before (verified on a live 8.4 server).
     *
     * @throws SqlError When the statement is SHOW PARSE_TREE
     */
    public function debugOnly(Node $tree, string $statement): void
    {
        $previous = null;
        foreach ($tree->tokens() as $token) {
            $shown = $previous?->name === 'SHOW';
            $previous = $token;
            if ($token->name === 'PARSE_TREE_SYM' && $shown) {
                $offset = $token->offset;
                $line = substr_count(substr($statement, 0, $offset), "\n") + 1;

                throw new SqlError(StatementError::ParseError, StatementError::ParseError->message(mb_strcut(substr($statement, $offset), 0, 80, 'UTF-8'), $line), new SyntaxException($token, [], ''));
            }
        }
    }

    /**
     * Refuses a DATE, TIME or TIMESTAMP literal whose text is no value of its form (ER_WRONG_VALUE).
     *
     * The parser of the server reads the literal, so the error comes before any name of the
     * statement is resolved. NO_ZERO_DATE and NO_ZERO_IN_DATE refuse zero dates and zero parts.
     * The error quotes the first 128 bytes of the text (verified on a live 8.4 server).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-literals.html.
     *
     * @throws SqlError When a temporal literal holds no value of its form
     */
    public function temporals(Node $tree, SqlModes $modes, ?Session $session = null, string $statement = ''): void
    {
        foreach ($tree->find('temporal_literal') as $literal) {
            $tokens = $literal->tokens();
            if (count($tokens) !== 2) {
                continue;
            }
            $form = match ($tokens[0]->name) {
                'DATE_SYM' => 'DATE',
                'TIME_SYM' => 'TIME',
                default => 'DATETIME',
            };
            $text = $this->unquote($tokens[1]->text, !$modes->has('NO_BACKSLASH_ESCAPES'));
            if (Temporal::literal($form, $text, 6, $modes->has('NO_ZERO_DATE'), $modes->has('NO_ZERO_IN_DATE')) === null) {
                $error = DataError::WrongValue->error($form, substr($text, 0, 128));
                if ($session === null || !$session->settings()->legacy()) {
                    throw $error;
                }
                $variable = $this->variable($tree, $tokens[0]->offset, $session->program);
                throw $variable === null ? (new CacheOptions())->literal($error, $tokens[0]->offset, $statement, $session) : (new CacheOptions())->undeclared(\MySqlMemory\Error\Family\ProgramError::UndeclaredVariable->error($variable), $statement, $session);
            }
        }
    }

    /**
     * Answers the text a quoted string token writes: the quotes removed, a doubled quote and the escape sequences read.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/string-literals.html.
     */
    public function unquote(string $token, bool $backslashes): string
    {
        $quote = $token[0] ?? '';
        if (($quote !== "'" && $quote !== '"') || strlen($token) < 2) {
            return $token;
        }
        $body = str_replace($quote . $quote, $quote, substr($token, 1, -1));
        if (!$backslashes) {
            return $body;
        }
        $escapes = ['0' => "\0", 'b' => "\x08", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1A", '%' => '\\%', '_' => '\\_'];

        return (string) preg_replace_callback('/\\\\(.)/s', static fn (array $match): string => $escapes[$match[1]] ?? $match[1], $body);
    }

    /**
     * Answers the first variable an INTO written before an offset names that no stored program declares, which MySQL 5.6 and 5.7 refuse before what follows it (verified on live 5.6.51 and 5.7.44 servers), or null.
     */
    public function variable(Node $tree, int $offset, ?\MySqlMemory\Program\Activation $program): ?string
    {
        foreach ($tree->find('select_var_ident') as $target) {
            $tokens = $target->tokens();
            if (($tokens[0]->text ?? '@') === '@' || $tokens[0]->offset >= $offset) {
                continue;
            }
            $name = $this->identifier($tokens[0]->text);
            if ($program?->variable($name) === null) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Answers the error MySQL 5.6 and 5.7 raise for an INTO variable no stored program declares, which they find while they parse the query block, before they refuse INTO in a union operand but the last (verified on live 5.6.51 and 5.7.44 servers), or null.
     */
    public function undeclared(Node $tree, Throwable $failure, ?\MySqlMemory\Program\Activation $program): ?SqlError
    {
        if (!str_starts_with($failure->getMessage(), 'Incorrect usage of UNION and INTO')) {
            return null;
        }
        foreach ($tree->find('select_var_ident') as $target) {
            $tokens = $target->tokens();
            if (($tokens[0]->text ?? '@') === '@') {
                continue;
            }
            $name = $this->identifier($tokens[0]->text);
            if ($program?->variable($name) === null) {
                return \MySqlMemory\Error\Family\ProgramError::UndeclaredVariable->error($name);
            }
        }

        return null;
    }

    /**
     * Answers the name an identifier token writes, without its backquotes.
     */
    public function identifier(string $text): string
    {
        return str_starts_with($text, '`') ? str_replace('``', '`', substr($text, 1, -1)) : $text;
    }

    /**
     * Answers the parse error of a failure to read a statement; an attribute a generated column cannot have is ER_WRONG_USAGE, as the parser of the server refuses it (verified on a live 8.4 server). MySQL 5.6 and 5.7, whose lexer reads the word after WITH to tell WITH ROLLUP and WITH CUBE apart, report an error at WITH from the text after it (verified on live 5.6.51 and 5.7.44 servers).
     */
    public function error(Throwable $failure, string $statement, ?\SqlSemantics\Contract\GrammarRelease $release = null, string $following = ''): SqlError
    {
        $cause = $failure;
        while ($cause !== null && !$cause instanceof SyntaxException) {
            $cause = $cause->getPrevious();
        }
        if (!$cause instanceof SyntaxException && preg_match('/\AIncorrect usage of (.+) and generated column\z/', $failure->getMessage(), $usage) === 1) {
            return new SqlError(StatementError::WrongUsage, StatementError::WrongUsage->message($usage[1], 'generated column'), $failure);
        }
        if (!$cause instanceof SyntaxException) {
            $lexical = $failure instanceof LexicalException ? $failure : $failure->getPrevious();

            return $this->near($lexical instanceof LexicalException ? $lexical->offset : strlen($statement), $statement, $following, $failure);
        }
        $offset = $cause->token->offset;
        if (in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true) && in_array($cause->token->name, ['WITH', 'WITH_CUBE_SYM', 'WITH_ROLLUP_SYM'], true)) {
            $offset += 4 + strspn($statement, " \t\n\r\f\v", $offset + 4);
        }

        return $this->near($offset, $statement, $following, $failure);
    }
}
