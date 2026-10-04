<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility\Columns;

/**
 * The result columns of the SHOW statements about binary logs and replication.
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
final class ReplicationReports
{
    /**
     * The columns of each report by grammar releases.
     */
    public const ROWS = [
        'binary_logs' => [
            'mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Log_name:v|File_size:B|Encrypted:v',
            'mysql-5.6.51 mysql-5.7.44' => 'Log_name:v|File_size:B',
        ],
        'replica_hosts' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0' => 'Server_id:I|Host:v|Port:I|Master_id:I|Slave_UUID:v',
        ],
        'replicas' => [
            'mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Server_Id:I|Host:v|Port:I|Source_Id:I|Replica_UUID:v',
        ],
        'log_events' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Log_name:v|Pos:B|Event_type:v|Server_id:I|End_log_pos:B|Info:v',
        ],
        'log_status' => [
            'mysql-5.6.51 mysql-5.7.44 mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'File:v|Position:B|Binlog_Do_DB:v|Binlog_Ignore_DB:v|Executed_Gtid_Set:v',
        ],
        'slave_status' => [
            'mysql-5.6.51' => 'Slave_IO_State:v|Master_Host:v|Master_User:v|Master_Port:I|Connect_Retry:I|Master_Log_File:v|Read_Master_Log_Pos:B|Relay_Log_File:v|Relay_Log_Pos:B|Relay_Master_Log_File:v|Slave_IO_Running:v|Slave_SQL_Running:v|Replicate_Do_DB:v|Replicate_Ignore_DB:v|Replicate_Do_Table:v|Replicate_Ignore_Table:v|Replicate_Wild_Do_Table:v|Replicate_Wild_Ignore_Table:v|Last_Errno:I|Last_Error:v|Skip_Counter:I|Exec_Master_Log_Pos:B|Relay_Log_Space:B|Until_Condition:v|Until_Log_File:v|Until_Log_Pos:B|Master_SSL_Allowed:v|Master_SSL_CA_File:v|Master_SSL_CA_Path:v|Master_SSL_Cert:v|Master_SSL_Cipher:v|Master_SSL_Key:v|Seconds_Behind_Master:B|Master_SSL_Verify_Server_Cert:v|Last_IO_Errno:I|Last_IO_Error:v|Last_SQL_Errno:I|Last_SQL_Error:v|Replicate_Ignore_Server_Ids:v|Master_Server_Id:I|Master_UUID:v|Master_Info_File:v|SQL_Delay:I|SQL_Remaining_Delay:I|Slave_SQL_Running_State:v|Master_Retry_Count:B|Master_Bind:v|Last_IO_Error_Timestamp:v|Last_SQL_Error_Timestamp:v|Master_SSL_Crl:v|Master_SSL_Crlpath:v|Retrieved_Gtid_Set:v|Executed_Gtid_Set:v|Auto_Position:I',
            'mysql-5.7.44' => 'Slave_IO_State:v|Master_Host:v|Master_User:v|Master_Port:I|Connect_Retry:I|Master_Log_File:v|Read_Master_Log_Pos:B|Relay_Log_File:v|Relay_Log_Pos:B|Relay_Master_Log_File:v|Slave_IO_Running:v|Slave_SQL_Running:v|Replicate_Do_DB:v|Replicate_Ignore_DB:v|Replicate_Do_Table:v|Replicate_Ignore_Table:v|Replicate_Wild_Do_Table:v|Replicate_Wild_Ignore_Table:v|Last_Errno:I|Last_Error:v|Skip_Counter:I|Exec_Master_Log_Pos:B|Relay_Log_Space:B|Until_Condition:v|Until_Log_File:v|Until_Log_Pos:B|Master_SSL_Allowed:v|Master_SSL_CA_File:v|Master_SSL_CA_Path:v|Master_SSL_Cert:v|Master_SSL_Cipher:v|Master_SSL_Key:v|Seconds_Behind_Master:B|Master_SSL_Verify_Server_Cert:v|Last_IO_Errno:I|Last_IO_Error:v|Last_SQL_Errno:I|Last_SQL_Error:v|Replicate_Ignore_Server_Ids:v|Master_Server_Id:I|Master_UUID:v|Master_Info_File:v|SQL_Delay:I|SQL_Remaining_Delay:I|Slave_SQL_Running_State:v|Master_Retry_Count:B|Master_Bind:v|Last_IO_Error_Timestamp:v|Last_SQL_Error_Timestamp:v|Master_SSL_Crl:v|Master_SSL_Crlpath:v|Retrieved_Gtid_Set:v|Executed_Gtid_Set:v|Auto_Position:I|Replicate_Rewrite_DB:v|Channel_Name:v|Master_TLS_Version:v',
            'mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0' => 'Slave_IO_State:v|Master_Host:v|Master_User:v|Master_Port:I|Connect_Retry:I|Master_Log_File:v|Read_Master_Log_Pos:B|Relay_Log_File:v|Relay_Log_Pos:B|Relay_Master_Log_File:v|Slave_IO_Running:v|Slave_SQL_Running:v|Replicate_Do_DB:v|Replicate_Ignore_DB:v|Replicate_Do_Table:v|Replicate_Ignore_Table:v|Replicate_Wild_Do_Table:v|Replicate_Wild_Ignore_Table:v|Last_Errno:I|Last_Error:v|Skip_Counter:I|Exec_Master_Log_Pos:B|Relay_Log_Space:B|Until_Condition:v|Until_Log_File:v|Until_Log_Pos:B|Master_SSL_Allowed:v|Master_SSL_CA_File:v|Master_SSL_CA_Path:v|Master_SSL_Cert:v|Master_SSL_Cipher:v|Master_SSL_Key:v|Seconds_Behind_Master:B|Master_SSL_Verify_Server_Cert:v|Last_IO_Errno:I|Last_IO_Error:v|Last_SQL_Errno:I|Last_SQL_Error:v|Replicate_Ignore_Server_Ids:v|Master_Server_Id:I|Master_UUID:v|Master_Info_File:v|SQL_Delay:I|SQL_Remaining_Delay:I|Slave_SQL_Running_State:v|Master_Retry_Count:B|Master_Bind:v|Last_IO_Error_Timestamp:v|Last_SQL_Error_Timestamp:v|Master_SSL_Crl:v|Master_SSL_Crlpath:v|Retrieved_Gtid_Set:v|Executed_Gtid_Set:v|Auto_Position:I|Replicate_Rewrite_DB:v|Channel_Name:v|Master_TLS_Version:v|Master_public_key_path:v|Get_master_public_key:I|Network_Namespace:v',
        ],
        'replica_status' => [
            'mysql-8.0.44 mysql-8.1.0 mysql-8.2.0 mysql-8.3.0 mysql-8.4.7 mysql-9.0.1 mysql-9.1.0' => 'Replica_IO_State:v|Source_Host:v|Source_User:v|Source_Port:I|Connect_Retry:I|Source_Log_File:v|Read_Source_Log_Pos:B|Relay_Log_File:v|Relay_Log_Pos:B|Relay_Source_Log_File:v|Replica_IO_Running:v|Replica_SQL_Running:v|Replicate_Do_DB:v|Replicate_Ignore_DB:v|Replicate_Do_Table:v|Replicate_Ignore_Table:v|Replicate_Wild_Do_Table:v|Replicate_Wild_Ignore_Table:v|Last_Errno:I|Last_Error:v|Skip_Counter:I|Exec_Source_Log_Pos:B|Relay_Log_Space:B|Until_Condition:v|Until_Log_File:v|Until_Log_Pos:B|Source_SSL_Allowed:v|Source_SSL_CA_File:v|Source_SSL_CA_Path:v|Source_SSL_Cert:v|Source_SSL_Cipher:v|Source_SSL_Key:v|Seconds_Behind_Source:B|Source_SSL_Verify_Server_Cert:v|Last_IO_Errno:I|Last_IO_Error:v|Last_SQL_Errno:I|Last_SQL_Error:v|Replicate_Ignore_Server_Ids:v|Source_Server_Id:I|Source_UUID:v|Source_Info_File:v|SQL_Delay:I|SQL_Remaining_Delay:I|Replica_SQL_Running_State:v|Source_Retry_Count:B|Source_Bind:v|Last_IO_Error_Timestamp:v|Last_SQL_Error_Timestamp:v|Source_SSL_Crl:v|Source_SSL_Crlpath:v|Retrieved_Gtid_Set:v|Executed_Gtid_Set:v|Auto_Position:I|Replicate_Rewrite_DB:v|Channel_Name:v|Source_TLS_Version:v|Source_public_key_path:v|Get_Source_public_key:I|Network_Namespace:v',
        ],
    ];
}
