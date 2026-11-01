<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

/**
 * The default encryption setting for tables created in a database.
 *
 * SQL validates Y/N before the first NUL, but only an entire N operand disables
 * encryption. A NUL suffix therefore enables it, even after N. Letter case is
 * ignored. Observed through SQL on MySQL 8.0.44, 8.4.7 and 9.1.0.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-database.html.
 *
 * @visibility public
 * @example Enabling default encryption
 *     \SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption::Enabled->value // => 'Y'
 */
enum Encryption: string
{
    case Enabled = 'Y';
    case Disabled = 'N';

    /**
     * Converts a decoded quoted operand, or answers null outside its input domain.
     */
    public static function fromOperand(string $value): ?self
    {
        if (!in_array(strtoupper(explode("\0", $value, 2)[0]), ['Y', 'N'], true)) {
            return null;
        }

        return strtoupper($value) === 'N' ? self::Disabled : self::Enabled;
    }
}
