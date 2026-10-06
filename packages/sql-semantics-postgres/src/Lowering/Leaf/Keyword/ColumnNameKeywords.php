<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Keyword;

/**
 * The productions of `col_name_keyword`: the keywords that may be used as a column, table or alias name but not as a function or type name.
 *
 * Rule: PG-KEYWORD-NAME-001 (table). The list is the union of the shipped
 * grammar releases; each production reads one keyword terminal as a name.
 * Source: https://www.postgresql.org/docs/17/sql-keywords-appendix.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ColumnNameKeywords
{
    /**
     * The production signatures, one per keyword.
     */
    public const SIGNATURES = [
        'col_name_keyword: BETWEEN', 'col_name_keyword: BIGINT', 'col_name_keyword: BIT', 'col_name_keyword: BOOLEAN_P',
        'col_name_keyword: CHARACTER', 'col_name_keyword: CHAR_P', 'col_name_keyword: COALESCE', 'col_name_keyword: DEC',
        'col_name_keyword: DECIMAL_P', 'col_name_keyword: EXISTS', 'col_name_keyword: EXTRACT', 'col_name_keyword: FLOAT_P',
        'col_name_keyword: GREATEST', 'col_name_keyword: GROUPING', 'col_name_keyword: INOUT', 'col_name_keyword: INTEGER',
        'col_name_keyword: INTERVAL', 'col_name_keyword: INT_P', 'col_name_keyword: JSON', 'col_name_keyword: JSON_ARRAY',
        'col_name_keyword: JSON_ARRAYAGG', 'col_name_keyword: JSON_EXISTS', 'col_name_keyword: JSON_OBJECT', 'col_name_keyword: JSON_OBJECTAGG',
        'col_name_keyword: JSON_QUERY', 'col_name_keyword: JSON_SCALAR', 'col_name_keyword: JSON_SERIALIZE', 'col_name_keyword: JSON_TABLE',
        'col_name_keyword: JSON_VALUE', 'col_name_keyword: LEAST', 'col_name_keyword: MERGE_ACTION', 'col_name_keyword: NATIONAL',
        'col_name_keyword: NCHAR', 'col_name_keyword: NONE', 'col_name_keyword: NORMALIZE', 'col_name_keyword: NULLIF',
        'col_name_keyword: NUMERIC', 'col_name_keyword: OUT_P', 'col_name_keyword: OVERLAY', 'col_name_keyword: POSITION',
        'col_name_keyword: PRECISION', 'col_name_keyword: REAL', 'col_name_keyword: ROW', 'col_name_keyword: SETOF',
        'col_name_keyword: SMALLINT', 'col_name_keyword: SUBSTRING', 'col_name_keyword: TIME', 'col_name_keyword: TIMESTAMP',
        'col_name_keyword: TREAT', 'col_name_keyword: TRIM', 'col_name_keyword: VALUES', 'col_name_keyword: VARCHAR',
        'col_name_keyword: XMLATTRIBUTES', 'col_name_keyword: XMLCONCAT', 'col_name_keyword: XMLELEMENT', 'col_name_keyword: XMLEXISTS',
        'col_name_keyword: XMLFOREST', 'col_name_keyword: XMLNAMESPACES', 'col_name_keyword: XMLPARSE', 'col_name_keyword: XMLPI',
        'col_name_keyword: XMLROOT', 'col_name_keyword: XMLSERIALIZE', 'col_name_keyword: XMLTABLE',
    ];
}
