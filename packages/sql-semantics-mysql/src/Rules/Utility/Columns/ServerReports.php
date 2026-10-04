<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility\Columns;

/**
 * The result columns of the SHOW statements about the server, the session, accounts and diagnostics.
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
final class ServerReports
{
    /**
     * The columns of each report by grammar releases.
     */
    public const ROWS = [
        'plugins' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Name:v|Status:v|Type:v|Library:v?|License:v?',
        ],
        'engine' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Type:v|Name:v|Status:v',
        ],
        'engines' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Engine:v|Support:v|Comment:v|Transactions:v?|XA:v?|Savepoints:v?',
        ],
        'warning_count' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => '@@session.warning_count:B?',
        ],
        'error_count' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => '@@session.error_count:B?',
        ],
        'diagnostics' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Level:v|Code:I|Message:v',
        ],
        'profiles' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Query_ID:I|Duration:F|Query:v',
        ],
        'profile' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Status:v|Duration:d|CPU_user:d?|CPU_system:d?|Context_voluntary:i?|Context_involuntary:i?|Block_ops_in:i?|Block_ops_out:i?|Messages_sent:i?|Messages_received:i?|Page_faults_major:i?|Page_faults_minor:i?|Swaps:i?|Source_function:v?|Source_file:v?|Source_line:i?',
        ],
        'variables' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Variable_name:v|Value:v?',
        ],
        'processlist' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Id:b|User:v|Host:v|db:v?|Command:v|Time:i|State:v?|Info:v?',
        ],
        'processlist_full' => [
            'mysql-5.6.51 mysql-5.7.44' => 'Id:b|User:v|Host:v|db:v?|Command:v|Time:i|State:v?|Info:m?',
            'mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Id:b|User:v|Host:v|db:v?|Command:v|Time:i|State:v?|Info:l?',
        ],
        'charset' => [
            'mysql-5.6.51 mysql-5.7.44' => 'Charset:v|Description:v|Default collation:v|Maxlen:b',
            'mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Charset:v|Description:v|Default collation:v|Maxlen:I',
        ],
        'collation' => [
            'mysql-5.6.51 mysql-5.7.44' => 'Collation:v|Charset:v|Id:b|Default:v|Compiled:v|Sortlen:b',
            'mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Collation:v|Charset:v|Id:B|Default:v|Compiled:v|Sortlen:I|Pad_attribute:c',
        ],
        'privileges' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Privilege:v|Context:v|Comment:v',
        ],
        'grants' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Grants for :v',
        ],
        'create_user' => [
            'mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'CREATE USER for :v',
        ],
    ];
}
