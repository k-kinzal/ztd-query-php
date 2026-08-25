<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

/**
 * Reads a bare table name written in a plan.
 *
 * A plan may name a table that stands alone, with no relation to anything, and
 * such a name is written as itself. Accepting anything there would let a
 * mistyped relation — one missing its dot, say — be read as a table nobody
 * meant, and the mistake would only surface as a schema lookup that fails.
 */
final class TableName
{
    private const TABLE_NAME = '/^(?:`[^`]+`|"[^"]+"|[A-Za-z_][A-Za-z0-9_$]*)$/';

    /**
     * Reads a table name, with its quotes removed.
     *
     * @param string $written Name as the plan writes it
     *
     * @return string The name alone
     *
     * @throws PlanSyntaxException When the text is not a table name
     */
    public static function of(string $written): string
    {
        $name = trim($written);
        if (preg_match(self::TABLE_NAME, $name) !== 1) {
            throw PlanSyntaxException::notATableName($written);
        }

        return trim($name, '`"');
    }
}
