<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Noise;

/**
 * The token positions of the catalog family that carry no meaning.
 *
 * A position is listed only when the token there has no effect on what the
 * statement requests in that production, and each entry states the reason and
 * cites the manual. A significant token never belongs here: when rendering
 * cannot reproduce it, the model lacks a distinction.
 *
 * @visibility SqlSemantics
 */
final class CatalogNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            // The synopsis writes every option as `option [=] value`: the equals sign is optional. https://www.postgresql.org/docs/17/sql-createdatabase.html https://www.postgresql.org/docs/17/sql-alterdatabase.html
            'opt_equal: =' => [0],
            // The synopsis writes `ALTER DATABASE name [ [ WITH ] option [ ... ] ]`: WITH is optional. https://www.postgresql.org/docs/17/sql-alterdatabase.html
            'AlterDatabaseStmt: ALTER DATABASE name WITH createdb_opt_list' => [3],
        ];
    }
}
