<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Noise;

/**
 * The token positions of the manipulation family that carry no meaning.
 *
 * A position is listed only when the token there has no effect on what the
 * statement requests in that production, and each entry states the reason and
 * cites the manual. A significant token never belongs here: when rendering
 * cannot reproduce it, the model lacks a distinction.
 *
 * @visibility SqlSemantics
 */
final class ManipulationNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            // "The AS keyword is optional" before the alias of the target table. https://www.postgresql.org/docs/17/sql-update.html
            'relation_expr_opt_alias: relation_expr AS ColId' => [1],
            // NOT MATCHED BY TARGET is the same as NOT MATCHED. https://www.postgresql.org/docs/17/sql-merge.html
            'merge_when_tgt_not_matched: WHEN NOT MATCHED BY TARGET' => [3, 4],
            // "PREPARE: This key word is ignored." https://www.postgresql.org/docs/17/sql-deallocate.html
            'DeallocateStmt: DEALLOCATE PREPARE name' => [1],
            'DeallocateStmt: DEALLOCATE PREPARE ALL' => [1],
            // FROM and IN before the cursor name are noise words of FETCH and MOVE. https://www.postgresql.org/docs/17/sql-fetch.html
            'from_in: FROM' => [0],
            'from_in: IN_P' => [0],
            // [ USING ] DELIMITERS: USING is optional in the old COPY syntax. https://www.postgresql.org/docs/17/sql-copy.html#id-1.9.3.55.10
            'opt_using: USING' => [0],
        ];
    }
}
