<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Noise;

/**
 * The token positions of the routine family that carry no meaning.
 *
 * A position is listed only when the token there has no effect on what the
 * statement requests in that production, and each entry states the reason and
 * cites the manual. A significant token never belongs here: when rendering
 * cannot reproduce it, the model lacks a distinction.
 *
 * @visibility SqlSemantics
 */
final class RoutineNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            // "The key word EXTERNAL is allowed for SQL conformance, but it is optional since, unlike in SQL, this feature applies to all functions not only external ones." https://www.postgresql.org/docs/17/sql-createfunction.html
            'common_func_opt_item: EXTERNAL SECURITY DEFINER' => [0],
            'common_func_opt_item: EXTERNAL SECURITY INVOKER' => [0],
            // RESTRICT: "Ignored for conformance with the SQL standard." https://www.postgresql.org/docs/17/sql-alterfunction.html
            'opt_restrict: RESTRICT' => [0],
            // "The RECHECK clause ... is obsolete": RECHECK is accepted and ignored with a notice. https://www.postgresql.org/docs/17/sql-createopclass.html (Compatibility), gram.y opt_recheck
            'opt_recheck: RECHECK' => [0],
            // A semicolon ends each statement of a BEGIN ATOMIC block; an empty statement between semicolons is discarded, as in a statement list. https://www.postgresql.org/docs/17/sql-createfunction.html, gram.y routine_body_stmt_list
            'routine_body_stmt_list: routine_body_stmt_list routine_body_stmt ;' => [2],
        ];
    }
}
