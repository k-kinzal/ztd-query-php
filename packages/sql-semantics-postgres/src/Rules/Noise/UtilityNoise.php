<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Noise;

/**
 * The token positions of the utility family that carry no meaning.
 *
 * A position is listed only when the token there has no effect on what the
 * statement requests in that production, and each entry states the reason and
 * cites the manual. A significant token never belongs here: when rendering
 * cannot reproduce it, the model lacks a distinction.
 *
 * @visibility SqlSemantics
 */
final class UtilityNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            // "WORK, TRANSACTION: Optional key words. They have no effect." https://www.postgresql.org/docs/17/sql-begin.html https://www.postgresql.org/docs/17/sql-commit.html https://www.postgresql.org/docs/17/sql-rollback.html https://www.postgresql.org/docs/17/sql-end.html https://www.postgresql.org/docs/17/sql-abort.html
            'opt_transaction: WORK' => [0],
            'opt_transaction: TRANSACTION' => [0],
            // The synopsis writes `RELEASE [ SAVEPOINT ] savepoint_name`: the key word is optional. https://www.postgresql.org/docs/17/sql-release-savepoint.html
            'TransactionStmt: RELEASE SAVEPOINT ColId' => [1],
            // The synopsis writes `ROLLBACK [ WORK | TRANSACTION ] TO [ SAVEPOINT ] savepoint_name`: the key word is optional. https://www.postgresql.org/docs/17/sql-rollback-to.html
            'TransactionStmt: ROLLBACK opt_transaction TO SAVEPOINT ColId' => [3],
            // The synopsis writes `transaction_mode [, ...]`, and "the transaction modes may be written with or without commas" in the grammar: the comma separates and nothing more. https://www.postgresql.org/docs/17/sql-set-transaction.html https://www.postgresql.org/docs/17/sql-begin.html
            'transaction_mode_list: transaction_mode_list , transaction_mode_item' => [1],
            // The synopsis writes `configuration_parameter { TO | = } { value | 'value' | DEFAULT }`: either word introduces the value. https://www.postgresql.org/docs/17/sql-set.html
            'generic_set: var_name TO var_list' => [1],
            'generic_set: var_name = var_list' => [1],
            'generic_set: var_name TO DEFAULT' => [1],
            'generic_set: var_name = DEFAULT' => [1],
            // "SESSION: Specifies that the command takes effect for the current session. (This is the default if neither SESSION nor LOCAL appears.)" https://www.postgresql.org/docs/17/sql-set.html
            'VariableSetStmt: SET SESSION set_rest' => [1],
        ];
    }
}
