<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlTokenStream;
use ZtdQuery\Sql\TransactionStatement;
use ZtdQuery\Sql\TransactionStatementParser;

/**
 * Implements the My Sql Transaction Statement Parser contract for MySQL.
 */
final class MySqlTransactionStatementParser implements TransactionStatementParser
{
    /**
     * Parse for the supplied MySQL input.
     */
    public function parse(string $sql): ?TransactionStatement
    {
        $tokens = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens();
        if (($tokens[count($tokens) - 1] ?? null)?->text === ';') {
            array_pop($tokens);
        }
        if ((new Parsing\Transaction\TokenReader())->matchesAny($tokens, [['BEGIN'], ['BEGIN', 'WORK'], ['START', 'TRANSACTION']])) {
            return TransactionStatement::begin();
        }
        if ((new Parsing\Transaction\TokenReader())->matchesAny($tokens, [['COMMIT'], ['COMMIT', 'WORK']])) {
            return TransactionStatement::commit();
        }
        if ((new Parsing\Transaction\TokenReader())->matchesAny($tokens, [['ROLLBACK'], ['ROLLBACK', 'WORK']])) {
            return TransactionStatement::rollback();
        }
        $name = (new Parsing\Transaction\TokenReader())->nameAfter($tokens, [['SAVEPOINT']]);
        if ($name !== null) {
            return TransactionStatement::savepoint($name);
        }
        $name = (new Parsing\Transaction\TokenReader())->nameAfter($tokens, [['ROLLBACK', 'TO'], ['ROLLBACK', 'TO', 'SAVEPOINT']]);
        if ($name !== null) {
            return TransactionStatement::rollbackTo($name);
        }
        $name = (new Parsing\Transaction\TokenReader())->nameAfter($tokens, [['RELEASE', 'SAVEPOINT']]);

        return $name !== null ? TransactionStatement::release($name) : null;
    }

}
