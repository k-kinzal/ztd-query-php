<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Parse;

use ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;
use ZtdQuery\Sql\TransactionStatement;
use ZtdQuery\Sql\TransactionStatementParser;

/**
 * The pg sql transaction statement parser, as transaction statement parser.
 */
final class PgSqlTransactionStatementParser implements TransactionStatementParser
{
    /**
     * Reads.
     *
     * @param string $sql
     * @return ?TransactionStatement
     */
    public function parse(string $sql): ?TransactionStatement
    {
        $tokens = SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create())->significantTokens();
        if (($tokens[count($tokens) - 1] ?? null)?->text === ';') {
            array_pop($tokens);
        }
        if ($this->matchesAny($tokens, [['BEGIN'], ['BEGIN', 'WORK'], ['BEGIN', 'TRANSACTION'], ['START', 'TRANSACTION']])) {
            return TransactionStatement::begin();
        }
        if ($this->matchesAny($tokens, [['COMMIT'], ['COMMIT', 'WORK'], ['COMMIT', 'TRANSACTION'], ['END'], ['END', 'WORK'], ['END', 'TRANSACTION']])) {
            return TransactionStatement::commit();
        }
        if ($this->matchesAny($tokens, [['ROLLBACK'], ['ROLLBACK', 'WORK'], ['ROLLBACK', 'TRANSACTION']])) {
            return TransactionStatement::rollback();
        }
        $name = $this->nameAfter($tokens, [['SAVEPOINT']]);
        if ($name !== null) {
            return TransactionStatement::savepoint($name);
        }
        $name = $this->nameAfter($tokens, [['ROLLBACK', 'TO'], ['ROLLBACK', 'TO', 'SAVEPOINT']]);
        if ($name !== null) {
            return TransactionStatement::rollbackTo($name);
        }
        $name = $this->nameAfter($tokens, [['RELEASE'], ['RELEASE', 'SAVEPOINT']]);

        return $name !== null ? TransactionStatement::release($name) : null;
    }

    /**
     * Reports whether the tokens spell any one of these statements.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param list<list<string>> $forms The forms
     *
     * @return bool What it answers
     */
    public function matchesAny(array $tokens, array $forms): bool
    {
        foreach ($forms as $form) {
            if ($this->matches($tokens, $form)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the name written after one of these openings.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param list<list<string>> $prefixes The prefixes
     *
     * @return string|null What it answers
     */
    public function nameAfter(array $tokens, array $prefixes): ?string
    {
        foreach ($prefixes as $prefix) {
            if (count($tokens) !== count($prefix) + 1 || !$this->matches(array_slice($tokens, 0, -1), $prefix)) {
                continue;
            }
            $name = $tokens[count($prefix)];
            if (!in_array($name->kind, [SqlTokenKind::Word, SqlTokenKind::QuotedIdentifier], true)) {
                return null;
            }

            return $this->unquote($name->text);
        }

        return null;
    }

    /**
     * Reports whether the tokens are exactly these keywords.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param list<string> $keywords Keywords to look for, in order
     *
     * @return bool What it answers
     */
    public function matches(array $tokens, array $keywords): bool
    {
        if (count($tokens) !== count($keywords)) {
            return false;
        }
        foreach ($keywords as $index => $keyword) {
            if (!$tokens[$index]->isKeyword($keyword)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Answers the name a quoted identifier stands for.
     *
     * A name that opens with a quote and never closes is not a name at all,
     * and answering it unquoted would invent one that was never written.
     *
     * @param string $identifier Name, as it was written
     *
     * @return string|null What it answers
     */
    public function unquote(string $identifier): ?string
    {
        $first = $identifier[0] ?? '';
        if ($first === '`') {
            return null;
        }
        if ($first !== '"') {
            return $identifier;
        }
        if (($identifier[strlen($identifier) - 1] ?? '') !== '"') {
            return null;
        }

        return str_replace('""', '"', substr($identifier, 1, -1));
    }
}
