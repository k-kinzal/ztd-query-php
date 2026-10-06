<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility\Columns;

/**
 * The result columns of EXPLAIN for a statement.
 *
 * Each report maps the space-separated grammar releases it applies to onto
 * its columns in order, written `name:code` and separated by `|`; the code
 * is decoded by MYSQL-SHOW-ROWS-001 (ShowRows), and `?` marks a column that
 * can be NULL. The tables were read from the result metadata the servers of
 * the shipped releases (5.6.51, 5.7.44, 8.0.46 for 8.0.44, 8.1.0, 8.2.0,
 * 8.3.0, 8.4.7, 9.0.1, 9.1.0) return for each statement, with a
 * placeholder for a column name the statement computes.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Utility
 */
final class ExplainReports
{
    /**
     * The columns of each report by grammar releases.
     */
    public const ROWS = [
        'explain' => [
            'mysql-5.6.51' => 'id:B|select_type:v|table:v?|type:v?|possible_keys:v?|key:v?|key_len:v?|ref:v?|rows:B?|Extra:v',
            'mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'id:B|select_type:v|table:v?|partitions:m?|type:v?|possible_keys:v?|key:v?|key_len:v?|ref:v?|rows:B?|filtered:f?|Extra:v',
        ],
        'explain_extended' => [
            'mysql-5.6.51' => 'id:B|select_type:v|table:v?|type:v?|possible_keys:v?|key:v?|key_len:v?|ref:v?|rows:B?|filtered:f?|Extra:v',
            'mysql-5.7.44' => 'id:B|select_type:v|table:v?|partitions:m?|type:v?|possible_keys:v?|key:v?|key_len:v?|ref:v?|rows:B?|filtered:f?|Extra:v',
        ],
        'explain_partitions' => [
            'mysql-5.6.51' => 'id:B|select_type:v|table:v?|partitions:m?|type:v?|possible_keys:v?|key:v?|key_len:v?|ref:v?|rows:B?|Extra:v',
            'mysql-5.7.44' => 'id:B|select_type:v|table:v?|partitions:m?|type:v?|possible_keys:v?|key:v?|key_len:v?|ref:v?|rows:B?|filtered:f?|Extra:v',
        ],
        'explain_document' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'EXPLAIN:v',
        ],
    ];
}
