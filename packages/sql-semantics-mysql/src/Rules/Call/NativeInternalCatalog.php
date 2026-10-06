<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

/**
 * The native functions MySQL 8.0 and later reserve for the views of the data dictionary.
 *
 * Part of MYSQL-NATIVE-FUNCTIONS-001. Every data dictionary name of the `func_array` of `sql/item_create.cc`, which the server rejects outside a system view. Each row
 * of a name is a release mask (bit 0 for 5.6.51 to bit 8 for 9.1.0; bit 9
 * marks a function the server reserves for its own views), the minimum and
 * maximum number of arguments (-1 for any number) and the result code of
 * MYSQL-CALL-RESULT-001. A function whose result or argument count changed
 * between releases has one row per range of releases.
 * Source: https://github.com/mysql/mysql-server/blob/8.4/sql/item_create.cc,
 * https://dev.mysql.com/doc/refman/8.4/en/built-in-function-reference.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class NativeInternalCatalog
{
    /**
     * The rows by upper-case function name.
     */
    public const ROWS = [
        'CAN_ACCESS_COLUMN' => [[1020, 3, 3, 'IY']], 'CAN_ACCESS_DATABASE' => [[1020, 1, 1, 'IY']], 'CAN_ACCESS_EVENT' => [[1020, 1, 1, 'IY']],
        'CAN_ACCESS_RESOURCE_GROUP' => [[1020, 1, 1, 'IY']], 'CAN_ACCESS_ROUTINE' => [[1020, 5, 5, 'IY']], 'CAN_ACCESS_TABLE' => [[1020, 2, 2, 'IY']],
        'CAN_ACCESS_TRIGGER' => [[1020, 2, 2, 'IY']], 'CAN_ACCESS_USER' => [[1020, 2, 2, 'IY']], 'CAN_ACCESS_VIEW' => [[1020, 4, 4, 'IY']],
        'GET_DD_COLUMN_PRIVILEGES' => [[1020, 3, 3, 'SY']], 'GET_DD_CREATE_OPTIONS' => [[1020, 3, 3, 'SY']],
        'GET_DD_INDEX_PRIVATE_DATA' => [[1020, 2, 2, 'SY']], 'GET_DD_INDEX_SUB_PART_LENGTH' => [[1020, 5, 5, 'IY']],
        'GET_DD_PROPERTY_KEY_VALUE' => [[1020, 2, 2, 'SY']], 'GET_DD_SCHEMA_OPTIONS' => [[1020, 1, 1, 'SY']],
        'GET_DD_TABLESPACE_PRIVATE_DATA' => [[1020, 2, 2, 'SY']], 'INTERNAL_AUTO_INCREMENT' => [[1020, 9, 10, 'IY']],
        'INTERNAL_AVG_ROW_LENGTH' => [[1020, 8, 9, 'IY']], 'INTERNAL_CHECKSUM' => [[1020, 8, 9, 'IY']], 'INTERNAL_CHECK_TIME' => [[1020, 8, 9, 'EY']],
        'INTERNAL_DATA_FREE' => [[1020, 8, 9, 'IY']], 'INTERNAL_DATA_LENGTH' => [[1020, 8, 9, 'IY']], 'INTERNAL_DD_CHAR_LENGTH' => [[1020, 4, 4, 'IY']],
        'INTERNAL_GET_COMMENT_OR_ERROR' => [[1020, 5, 5, 'SY']], 'INTERNAL_GET_DD_COLUMN_EXTRA' => [[1020, 8, 8, 'SY']],
        'INTERNAL_GET_ENABLED_ROLE_JSON' => [[1020, 0, 0, 'SY']], 'INTERNAL_GET_HOSTNAME' => [[1020, 0, 1, 'SY']],
        'INTERNAL_GET_MANDATORY_ROLES_JSON' => [[1020, 0, 0, 'SY']], 'INTERNAL_GET_PARTITION_NODEGROUP' => [[1020, 1, 1, 'SY']],
        'INTERNAL_GET_USERNAME' => [[1020, 0, 1, 'SY']], 'INTERNAL_GET_VIEW_WARNING_OR_ERROR' => [[1020, 4, 4, 'IY']],
        'INTERNAL_INDEX_COLUMN_CARDINALITY' => [[1020, 11, 11, 'IY']], 'INTERNAL_INDEX_LENGTH' => [[1020, 8, 9, 'IY']],
        'INTERNAL_IS_ENABLED_ROLE' => [[1020, 2, 2, 'IY']], 'INTERNAL_IS_MANDATORY_ROLE' => [[1020, 2, 2, 'IY']],
        'INTERNAL_KEYS_DISABLED' => [[1020, 1, 1, 'IY']], 'INTERNAL_MAX_DATA_LENGTH' => [[1020, 8, 9, 'IY']],
        'INTERNAL_TABLESPACE_AUTOEXTEND_SIZE' => [[1020, 4, 4, 'IY']], 'INTERNAL_TABLESPACE_DATA_FREE' => [[1020, 4, 4, 'IY']],
        'INTERNAL_TABLESPACE_EXTENT_SIZE' => [[1020, 4, 4, 'IY']], 'INTERNAL_TABLESPACE_EXTRA' => [[1020, 4, 4, 'SY']],
        'INTERNAL_TABLESPACE_FREE_EXTENTS' => [[1020, 4, 4, 'IY']], 'INTERNAL_TABLESPACE_ID' => [[1020, 4, 4, 'IY']],
        'INTERNAL_TABLESPACE_INITIAL_SIZE' => [[1020, 4, 4, 'IY']], 'INTERNAL_TABLESPACE_LOGFILE_GROUP_NAME' => [[1020, 4, 4, 'TY']],
        'INTERNAL_TABLESPACE_LOGFILE_GROUP_NUMBER' => [[1020, 4, 4, 'IY']], 'INTERNAL_TABLESPACE_MAXIMUM_SIZE' => [[1020, 4, 4, 'IY']],
        'INTERNAL_TABLESPACE_ROW_FORMAT' => [[1020, 4, 4, 'SY']], 'INTERNAL_TABLESPACE_STATUS' => [[1020, 4, 4, 'SY']],
        'INTERNAL_TABLESPACE_TOTAL_EXTENTS' => [[1020, 4, 4, 'IY']], 'INTERNAL_TABLESPACE_TYPE' => [[1020, 4, 4, 'SY']],
        'INTERNAL_TABLESPACE_VERSION' => [[1020, 4, 4, 'IY']], 'INTERNAL_TABLE_ROWS' => [[1020, 8, 9, 'IY']], 'INTERNAL_UPDATE_TIME' => [[1020, 8, 9, 'EY']],
        'INTERNAL_USE_TERMINOLOGY_PREVIOUS' => [[1008, 0, 0, 'IY']], 'IS_VISIBLE_DD_OBJECT' => [[1020, 1, 3, 'IY']],
        'REMOVE_DD_PROPERTY_KEY' => [[1020, 2, 2, 'SY']],
    ];
}
