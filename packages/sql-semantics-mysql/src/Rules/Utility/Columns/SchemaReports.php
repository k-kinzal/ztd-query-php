<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility\Columns;

/**
 * The result columns of the SHOW statements about databases, tables and stored programs.
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
final class SchemaReports
{
    /**
     * The columns of each report by grammar releases.
     */
    public const ROWS = [
        'databases' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Database:v',
        ],
        'tables' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Tables_in_:v',
        ],
        'tables_full' => [
            'mysql-5.6.51 mysql-5.7.44' => 'Tables_in_:v|Table_type:v',
            'mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Tables_in_:v|Table_type:c',
        ],
        'triggers' => [
            'mysql-5.6.51 mysql-5.7.44' => 'Trigger:v|Event:v|Table:v|Statement:m|Timing:v|Created:D?|sql_mode:v|Definer:v|character_set_client:v|collation_connection:v|Database Collation:v',
            'mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Trigger:v|Event:c|Table:v|Statement:l|Timing:c|Created:T|sql_mode:c|Definer:v|character_set_client:v|collation_connection:v|Database Collation:v',
        ],
        'events' => [
            'mysql-5.6.51 mysql-5.7.44' => 'Db:v|Name:v|Definer:v|Time zone:v|Type:v|Execute at:D?|Interval value:v?|Interval field:v?|Starts:D?|Ends:D?|Status:v|Originator:b|character_set_client:v|collation_connection:v|Database Collation:v',
            'mysql-8.0.44 mysql-8.1.0' => 'Db:v|Name:v|Definer:v|Time zone:v|Type:v|Execute at:D?|Interval value:v?|Interval field:c?|Starts:D?|Ends:D?|Status:c|Originator:I|character_set_client:v|collation_connection:v|Database Collation:v',
            'mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Db:v|Name:v|Definer:v|Time zone:v|Type:v|Execute at:D?|Interval value:v?|Interval field:c?|Starts:D?|Ends:D?|Status:v|Originator:I|character_set_client:v|collation_connection:v|Database Collation:v',
        ],
        'table_status' => [
            'mysql-5.6.51 mysql-5.7.44' => 'Name:v|Engine:v?|Version:B?|Row_format:v?|Rows:B?|Avg_row_length:B?|Data_length:B?|Max_data_length:B?|Index_length:B?|Data_free:B?|Auto_increment:B?|Create_time:D?|Update_time:D?|Check_time:D?|Collation:v?|Checksum:B?|Create_options:v?|Comment:v',
            'mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Name:v|Engine:v?|Version:i?|Row_format:c?|Rows:B?|Avg_row_length:B?|Data_length:B?|Max_data_length:B?|Index_length:B?|Data_free:B?|Auto_increment:B?|Create_time:T|Update_time:D?|Check_time:D?|Collation:v?|Checksum:b?|Create_options:v?|Comment:t?',
        ],
        'open_tables' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Database:v|Table:v|In_use:b|Name_locked:b',
        ],
        'columns' => [
            'mysql-5.6.51 mysql-5.7.44' => 'Field:v|Type:m|Null:v|Key:v|Default:m?|Extra:v',
            'mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Field:v?|Type:m|Null:v|Key:c|Default:t?|Extra:v?',
        ],
        'columns_full' => [
            'mysql-5.6.51 mysql-5.7.44' => 'Field:v|Type:m|Collation:v?|Null:v|Key:v|Default:m?|Extra:v|Privileges:v|Comment:v',
            'mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Field:v?|Type:m|Collation:v?|Null:v|Key:c|Default:t?|Extra:v?|Privileges:v?|Comment:t',
        ],
        'keys' => [
            'mysql-5.6.51 mysql-5.7.44' => 'Table:v|Non_unique:b|Key_name:v|Seq_in_index:b|Column_name:v|Collation:v?|Cardinality:b?|Sub_part:b?|Packed:v?|Null:v|Index_type:v|Comment:v?|Index_comment:v',
            'mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Table:v|Non_unique:i|Key_name:v?|Seq_in_index:I|Column_name:v?|Collation:v?|Cardinality:b?|Sub_part:b?|Packed:n?|Null:v|Index_type:v|Comment:v|Index_comment:v|Visible:v|Expression:l?',
        ],
        'create_database' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Database:v|Create Database:v',
        ],
        'create_table' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Table:v|Create Table:v',
        ],
        'create_view' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'View:v|Create View:v|character_set_client:v|collation_connection:v',
        ],
        'create_procedure' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Procedure:v|sql_mode:v|Create Procedure:v?|character_set_client:v|collation_connection:v|Database Collation:v',
        ],
        'create_function' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Function:v|sql_mode:v|Create Function:v?|character_set_client:v|collation_connection:v|Database Collation:v',
        ],
        'create_trigger' => [
            'mysql-5.6.51' => 'Trigger:v|sql_mode:v|SQL Original Statement:v?|character_set_client:v|collation_connection:v|Database Collation:v',
            'mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Trigger:v|sql_mode:v|SQL Original Statement:v?|character_set_client:v|collation_connection:v|Database Collation:v|Created:T',
        ],
        'create_event' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Event:v|sql_mode:v|time_zone:v|Create Event:v|character_set_client:v|collation_connection:v|Database Collation:v',
        ],
        'routine_status' => [
            'mysql-5.6.51 mysql-5.7.44' => 'Db:v|Name:v|Type:v|Definer:v|Modified:D|Created:D|Security_type:v|Comment:m|character_set_client:v|collation_connection:v|Database Collation:v',
            'mysql-8.0.44' => 'Db:v|Name:v|Type:c|Definer:v|Modified:T|Created:T|Security_type:c|Comment:t|character_set_client:v|collation_connection:v|Database Collation:v',
            'mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Db:v|Name:v|Type:c|Language:v|Definer:v|Modified:T|Created:T|Security_type:c|Comment:t|character_set_client:v|collation_connection:v|Database Collation:v',
        ],
        'routine_code' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Pos:B|Instruction:v',
        ],
    ];
}
