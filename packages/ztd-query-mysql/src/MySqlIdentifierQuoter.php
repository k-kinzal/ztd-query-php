<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\IdentifierQuoter;

/**
 * MySQL implementation of IdentifierQuoter.
 *
 * Uses backtick quoting (`identifier`).
 * Strips surrounding backticks to prevent double-quoting,
 * then escapes any remaining embedded backticks by doubling them.
 */
final class MySqlIdentifierQuoter implements IdentifierQuoter
{
    public function quote(string $identifier): string
    {
        $unquoted = $identifier;

        if (strlen($unquoted) >= 2 && $unquoted[0] === '`' && $unquoted[strlen($unquoted) - 1] === '`') {
            $unquoted = substr($unquoted, 1, -1);
            $unquoted = str_replace('``', '`', $unquoted);
        }

        $escaped = str_replace('`', '``', $unquoted);

        return '`' . $escaped . '`';
    }
}
