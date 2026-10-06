<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Keyword;

/**
 * The productions of `reserved_keyword`: the keywords that may be used as a name only after AS or a dot.
 *
 * Rule: PG-KEYWORD-NAME-001 (table). The list is the union of the shipped
 * grammar releases; each production reads one keyword terminal as a name.
 * Source: https://www.postgresql.org/docs/17/sql-keywords-appendix.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ReservedKeywords
{
    /**
     * The production signatures, one per keyword.
     */
    public const SIGNATURES = [
        'reserved_keyword: ALL', 'reserved_keyword: ANALYSE', 'reserved_keyword: ANALYZE', 'reserved_keyword: AND',
        'reserved_keyword: ANY', 'reserved_keyword: ARRAY', 'reserved_keyword: AS', 'reserved_keyword: ASC',
        'reserved_keyword: ASYMMETRIC', 'reserved_keyword: BOTH', 'reserved_keyword: CASE', 'reserved_keyword: CAST',
        'reserved_keyword: CHECK', 'reserved_keyword: COLLATE', 'reserved_keyword: COLUMN', 'reserved_keyword: CONSTRAINT',
        'reserved_keyword: CREATE', 'reserved_keyword: CURRENT_CATALOG', 'reserved_keyword: CURRENT_DATE', 'reserved_keyword: CURRENT_ROLE',
        'reserved_keyword: CURRENT_TIME', 'reserved_keyword: CURRENT_TIMESTAMP', 'reserved_keyword: CURRENT_USER', 'reserved_keyword: DEFAULT',
        'reserved_keyword: DEFERRABLE', 'reserved_keyword: DESC', 'reserved_keyword: DISTINCT', 'reserved_keyword: DO',
        'reserved_keyword: ELSE', 'reserved_keyword: END_P', 'reserved_keyword: EXCEPT', 'reserved_keyword: FALSE_P',
        'reserved_keyword: FETCH', 'reserved_keyword: FOR', 'reserved_keyword: FOREIGN', 'reserved_keyword: FROM',
        'reserved_keyword: GRANT', 'reserved_keyword: GROUP_P', 'reserved_keyword: HAVING', 'reserved_keyword: INITIALLY',
        'reserved_keyword: INTERSECT', 'reserved_keyword: INTO', 'reserved_keyword: IN_P', 'reserved_keyword: LATERAL_P',
        'reserved_keyword: LEADING', 'reserved_keyword: LIMIT', 'reserved_keyword: LOCALTIME', 'reserved_keyword: LOCALTIMESTAMP',
        'reserved_keyword: NOT', 'reserved_keyword: NULL_P', 'reserved_keyword: OFFSET', 'reserved_keyword: ON',
        'reserved_keyword: ONLY', 'reserved_keyword: OR', 'reserved_keyword: ORDER', 'reserved_keyword: PLACING',
        'reserved_keyword: PRIMARY', 'reserved_keyword: REFERENCES', 'reserved_keyword: RETURNING', 'reserved_keyword: SELECT',
        'reserved_keyword: SESSION_USER', 'reserved_keyword: SOME', 'reserved_keyword: SYMMETRIC', 'reserved_keyword: SYSTEM_USER',
        'reserved_keyword: TABLE', 'reserved_keyword: THEN', 'reserved_keyword: TO', 'reserved_keyword: TRAILING',
        'reserved_keyword: TRUE_P', 'reserved_keyword: UNION', 'reserved_keyword: UNIQUE', 'reserved_keyword: USER',
        'reserved_keyword: USING', 'reserved_keyword: VARIADIC', 'reserved_keyword: WHEN', 'reserved_keyword: WHERE',
        'reserved_keyword: WINDOW', 'reserved_keyword: WITH',
    ];
}
