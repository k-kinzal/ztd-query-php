<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Platform\SqlPlaceholderEscaper;

/**
 * An escaper that doubles every question mark.
 *
 * A rewritten statement may carry text a driver would otherwise read as a
 * positional placeholder. Which character that is, and how it is escaped,
 * belongs to the driver; doubling is enough to show what the contract is for.
 */
final class FakeSqlPlaceholderEscaper implements SqlPlaceholderEscaper
{
    /**
     * Writes the statement so a driver reads no placeholder that was not meant.
     *
     * @param string $sql Statement as it stands
     *
     * @return string The statement, with every placeholder character escaped
     */
    public function escape(string $sql): string
    {
        return str_replace('?', '??', $sql);
    }
}
