<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

/**
 * The native functions of MySQL that are not spatial and not internal to the data dictionary.
 *
 * Part of MYSQL-NATIVE-FUNCTIONS-001. Every name of the `func_array` of `sql/item_create.cc` in each release, other than the spatial and the data dictionary functions. Each row
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
final class NativeCatalog
{
    /**
     * The rows by upper-case function name.
     */
    public const ROWS = [
        'ABS' => [[511, 1, 1, 'HP']], 'ACOS' => [[511, 1, 1, 'DY']], 'ADDTIME' => [[511, 2, 2, 'XY']], 'AES_DECRYPT' => [[3, 2, 3, 'BY'], [508, 2, 6, 'BY']],
        'AES_ENCRYPT' => [[3, 2, 3, 'BY'], [508, 2, 6, 'BY']], 'ANY_VALUE' => [[510, 1, 1, '1F']], 'ASIN' => [[511, 1, 1, 'DY']],
        'ATAN' => [[511, 1, 2, 'DP']], 'ATAN2' => [[511, 1, 2, 'DP']], 'BENCHMARK' => [[511, 2, 2, 'IY']], 'BIN' => [[511, 1, 1, 'TP']],
        'BIN_TO_UUID' => [[508, 1, 2, 'TY']], 'BIT_COUNT' => [[511, 1, 1, 'IP']], 'BIT_LENGTH' => [[511, 1, 1, 'IP']], 'CEIL' => [[511, 1, 1, 'CP']],
        'CEILING' => [[511, 1, 1, 'CP']], 'CHARACTER_LENGTH' => [[511, 1, 1, 'IP']], 'CHAR_LENGTH' => [[511, 1, 1, 'IP']],
        'COERCIBILITY' => [[511, 1, 1, 'IN']], 'COMPRESS' => [[511, 1, 1, 'BY']], 'CONCAT' => [[511, 1, -1, 'SP']], 'CONCAT_WS' => [[511, 2, -1, 'SF']],
        'CONNECTION_ID' => [[511, 0, 0, 'UN']], 'CONV' => [[511, 3, 3, 'TY']], 'CONVERT_CPU_ID_MASK' => [[1020, 1, 1, 'TY']],
        'CONVERT_INTERVAL_TO_USER_INTERVAL' => [[1020, 2, 2, 'TY']], 'CONVERT_TZ' => [[511, 3, 3, 'EY']], 'COS' => [[511, 1, 1, 'DP']],
        'COT' => [[511, 1, 1, 'DY']], 'CRC32' => [[511, 1, 1, 'UP']], 'CURRENT_ROLE' => [[508, 0, 0, 'TN']], 'DATEDIFF' => [[511, 2, 2, 'IY']],
        'DATE_FORMAT' => [[511, 2, 2, 'TY']], 'DAYNAME' => [[511, 1, 1, 'TY']], 'DAYOFMONTH' => [[511, 1, 1, 'IY']], 'DAYOFWEEK' => [[511, 1, 1, 'IY']],
        'DAYOFYEAR' => [[511, 1, 1, 'IY']], 'DECODE' => [[3, 2, 2, 'BY']], 'DEGREES' => [[511, 1, 1, 'DP']], 'DES_DECRYPT' => [[3, 1, 2, 'BY']],
        'DES_ENCRYPT' => [[3, 1, 2, 'BY']], 'ELT' => [[511, 2, -1, 'SY']], 'ENCODE' => [[3, 2, 2, 'BY']], 'ENCRYPT' => [[3, 1, 2, 'SY']],
        'EXP' => [[511, 1, 1, 'DP']], 'EXPORT_SET' => [[511, 3, 5, 'SY']], 'EXTRACTVALUE' => [[511, 2, 2, 'SY']], 'FIELD' => [[511, 2, -1, 'IN']],
        'FIND_IN_SET' => [[511, 2, 2, 'IP']], 'FLOOR' => [[511, 1, 1, 'CP']], 'FORMAT_BYTES' => [[508, 1, 1, 'TY']], 'FORMAT_PICO_TIME' => [[508, 1, 1, 'TY']],
        'FOUND_ROWS' => [[511, 0, 0, 'IN']], 'FROM_BASE64' => [[511, 1, 1, 'BY']], 'FROM_DAYS' => [[511, 1, 1, 'AY']],
        'FROM_UNIXTIME' => [[511, 1, 1, 'EY'], [511, 2, 2, 'TY']], 'FROM_VECTOR' => [[384, 1, 1, 'TY']], 'GEOMCOLLFROMTEXT' => [[3, 1, 2, 'GY']],
        'GEOMCOLLFROMWKB' => [[3, 1, 2, 'GY']], 'GEOMETRYCOLLECTIONFROMTEXT' => [[3, 1, 2, 'GY']], 'GEOMETRYCOLLECTIONFROMWKB' => [[3, 1, 2, 'GY']],
        'GEOMETRYFROMTEXT' => [[3, 1, 2, 'GY']], 'GEOMETRYFROMWKB' => [[3, 1, 2, 'GY']], 'GEOMETRYN' => [[3, 2, 2, 'GY']], 'GEOMETRYTYPE' => [[3, 1, 1, 'TY']],
        'GEOMFROMTEXT' => [[3, 1, 2, 'GY']], 'GEOMFROMWKB' => [[3, 1, 2, 'GY']], 'GET_LOCK' => [[511, 2, 2, 'IY']], 'GREATEST' => [[511, 2, -1, '+P']],
        'GTID_SUBSET' => [[511, 2, 2, 'IY']], 'GTID_SUBTRACT' => [[511, 2, 2, 'TY']], 'HEX' => [[511, 1, 1, 'TP']], 'ICU_VERSION' => [[508, 0, 0, 'TN']],
        'IFNULL' => [[511, 2, 2, '+C']], 'INET6_ATON' => [[511, 1, 1, 'BY']], 'INET6_NTOA' => [[511, 1, 1, 'TY']], 'INET_ATON' => [[511, 1, 1, 'UY']],
        'INET_NTOA' => [[511, 1, 1, 'TY']], 'INSTR' => [[511, 2, 2, 'IP']], 'ISNULL' => [[511, 1, 1, 'IN']], 'IS_FREE_LOCK' => [[511, 1, 1, 'IY']],
        'IS_IPV4' => [[511, 1, 1, 'IY']], 'IS_IPV4_COMPAT' => [[511, 1, 1, 'IY']], 'IS_IPV4_MAPPED' => [[511, 1, 1, 'IY']], 'IS_IPV6' => [[511, 1, 1, 'IY']],
        'IS_USED_LOCK' => [[511, 1, 1, 'UY']], 'IS_UUID' => [[508, 1, 1, 'IY']], 'JSON_ARRAY' => [[510, 0, -1, 'JN']],
        'JSON_ARRAY_APPEND' => [[510, 3, -1, 'JY']], 'JSON_ARRAY_INSERT' => [[510, 3, -1, 'JY']], 'JSON_CONTAINS' => [[510, 2, 3, 'IY']],
        'JSON_CONTAINS_PATH' => [[510, 3, -1, 'IY']], 'JSON_DEPTH' => [[510, 1, 1, 'IY']], 'JSON_EXTRACT' => [[510, 2, -1, 'JY']],
        'JSON_INSERT' => [[510, 3, -1, 'JY']], 'JSON_KEYS' => [[510, 1, 2, 'JY']], 'JSON_LENGTH' => [[510, 1, 2, 'IY']], 'JSON_MERGE' => [[510, 2, -1, 'JY']],
        'JSON_MERGE_PATCH' => [[510, 2, -1, 'JY']], 'JSON_MERGE_PRESERVE' => [[510, 2, -1, 'JY']], 'JSON_OBJECT' => [[510, 0, -1, 'JN']],
        'JSON_OVERLAPS' => [[508, 2, 2, 'IY']], 'JSON_PRETTY' => [[510, 1, 1, 'TY']], 'JSON_QUOTE' => [[510, 1, 1, 'TY']],
        'JSON_REMOVE' => [[510, 2, -1, 'JY']], 'JSON_REPLACE' => [[510, 3, -1, 'JY']], 'JSON_SCHEMA_VALID' => [[508, 2, 2, 'IY']],
        'JSON_SCHEMA_VALIDATION_REPORT' => [[508, 2, 2, 'JY']], 'JSON_SEARCH' => [[510, 3, -1, 'JY']], 'JSON_SET' => [[510, 3, -1, 'JY']],
        'JSON_STORAGE_FREE' => [[508, 1, 1, 'IY']], 'JSON_STORAGE_SIZE' => [[510, 1, 1, 'IY']], 'JSON_TYPE' => [[510, 1, 1, 'TY']],
        'JSON_UNQUOTE' => [[510, 1, 1, 'TY']], 'JSON_VALID' => [[510, 1, 1, 'IP']], 'LAST_DAY' => [[511, 1, 1, 'AY']],
        'LAST_INSERT_ID' => [[511, 0, 0, 'UN'], [511, 1, 1, 'UY']], 'LCASE' => [[511, 1, 1, 'SP']], 'LEAST' => [[511, 2, -1, '+P']],
        'LENGTH' => [[511, 1, 1, 'IP']], 'LIKE_RANGE_MAX' => [[2, 2, 2, 'BY']], 'LIKE_RANGE_MIN' => [[2, 2, 2, 'BY']], 'LINEFROMTEXT' => [[3, 1, 2, 'GY']],
        'LINEFROMWKB' => [[3, 1, 2, 'GY']], 'LINESTRINGFROMTEXT' => [[3, 1, 2, 'GY']], 'LINESTRINGFROMWKB' => [[3, 1, 2, 'GY']], 'LN' => [[511, 1, 1, 'DY']],
        'LOAD_FILE' => [[511, 1, 1, 'BY']], 'LOCATE' => [[511, 2, 3, 'IP']], 'LOG' => [[511, 1, 2, 'DY']], 'LOG10' => [[511, 1, 1, 'DY']],
        'LOG2' => [[511, 1, 1, 'DY']], 'LOWER' => [[511, 1, 1, 'SP']], 'LPAD' => [[511, 3, 3, 'SY']], 'LTRIM' => [[511, 1, 1, 'SP']],
        'MAKEDATE' => [[511, 2, 2, 'AY']], 'MAKETIME' => [[511, 3, 3, 'MY']], 'MAKE_SET' => [[511, 2, -1, 'SY']],
        'MASTER_POS_WAIT' => [[1, 2, 3, 'IY'], [510, 2, 4, 'IY']], 'MD5' => [[511, 1, 1, 'TP']], 'MLINEFROMTEXT' => [[3, 1, 2, 'GY']],
        'MLINEFROMWKB' => [[3, 1, 2, 'GY']], 'MONTHNAME' => [[511, 1, 1, 'TY']], 'MPOINTFROMTEXT' => [[3, 1, 2, 'GY']], 'MPOINTFROMWKB' => [[3, 1, 2, 'GY']],
        'MPOLYFROMTEXT' => [[3, 1, 2, 'GY']], 'MPOLYFROMWKB' => [[3, 1, 2, 'GY']], 'MULTILINESTRINGFROMTEXT' => [[3, 1, 2, 'GY']],
        'MULTILINESTRINGFROMWKB' => [[3, 1, 2, 'GY']], 'MULTIPOINTFROMTEXT' => [[3, 1, 2, 'GY']], 'MULTIPOINTFROMWKB' => [[3, 1, 2, 'GY']],
        'MULTIPOLYGONFROMTEXT' => [[3, 1, 2, 'GY']], 'MULTIPOLYGONFROMWKB' => [[3, 1, 2, 'GY']], 'NAME_CONST' => [[511, 2, 2, '2L']],
        'NULLIF' => [[511, 2, 2, '1Y']], 'OCT' => [[511, 1, 1, 'TP']], 'OCTET_LENGTH' => [[511, 1, 1, 'IP']], 'ORD' => [[511, 1, 1, 'IP']],
        'PERIOD_ADD' => [[511, 2, 2, 'IP']], 'PERIOD_DIFF' => [[511, 2, 2, 'IP']], 'PI' => [[511, 0, 0, 'DN']], 'POINTFROMTEXT' => [[3, 1, 2, 'GY']],
        'POINTFROMWKB' => [[3, 1, 2, 'GY']], 'POINTN' => [[3, 2, 2, 'GY']], 'POLYFROMTEXT' => [[3, 1, 2, 'GY']], 'POLYFROMWKB' => [[3, 1, 2, 'GY']],
        'POLYGONFROMTEXT' => [[3, 1, 2, 'GY']], 'POLYGONFROMWKB' => [[3, 1, 2, 'GY']], 'POW' => [[511, 2, 2, 'DP']], 'POWER' => [[511, 2, 2, 'DP']],
        'PS_CURRENT_THREAD_ID' => [[508, 0, 0, 'UN']], 'PS_THREAD_ID' => [[508, 1, 1, 'UY']], 'QUOTE' => [[511, 1, 1, 'SN']], 'RADIANS' => [[511, 1, 1, 'DP']],
        'RAND' => [[511, 0, 1, 'DN']], 'RANDOM_BYTES' => [[511, 1, 1, 'BY']], 'REGEXP_INSTR' => [[508, 2, 6, 'IY']], 'REGEXP_LIKE' => [[508, 2, 3, 'IY']],
        'REGEXP_REPLACE' => [[508, 3, 6, 'SY']], 'REGEXP_SUBSTR' => [[508, 2, 5, 'SY']], 'RELEASE_ALL_LOCKS' => [[510, 0, 0, 'IN']],
        'RELEASE_LOCK' => [[511, 1, 1, 'IY']], 'REVERSE' => [[511, 1, 1, 'SP']], 'ROLES_GRAPHML' => [[508, 0, 0, 'TN']], 'ROUND' => [[511, 1, 2, 'HP']],
        'RPAD' => [[511, 3, 3, 'SY']], 'RTRIM' => [[511, 1, 1, 'SP']], 'SEC_TO_TIME' => [[511, 1, 1, 'MY']], 'SHA' => [[511, 1, 1, 'TP']],
        'SHA1' => [[511, 1, 1, 'TP']], 'SHA2' => [[511, 2, 2, 'TY']], 'SIGN' => [[511, 1, 1, 'IP']], 'SIN' => [[511, 1, 1, 'DP']],
        'SLEEP' => [[511, 1, 1, 'IY']], 'SOUNDEX' => [[511, 1, 1, 'SY']], 'SOURCE_POS_WAIT' => [[508, 2, 4, 'IY']], 'SPACE' => [[511, 1, 1, 'TY']],
        'SQRT' => [[511, 1, 1, 'DY']], 'STATEMENT_DIGEST' => [[508, 1, 1, 'TY']], 'STATEMENT_DIGEST_TEXT' => [[508, 1, 1, 'TY']],
        'STRCMP' => [[511, 2, 2, 'IP']], 'STRING_TO_VECTOR' => [[384, 1, 1, 'VY']], 'STR_TO_DATE' => [[511, 2, 2, 'KY']],
        'SUBSTRING_INDEX' => [[511, 3, 3, 'SP']], 'SUBTIME' => [[511, 2, 2, 'XY']], 'TAN' => [[511, 1, 1, 'DP']], 'TIMEDIFF' => [[511, 2, 2, 'MY']],
        'TIME_FORMAT' => [[511, 2, 2, 'TY']], 'TIME_TO_SEC' => [[511, 1, 1, 'IY']], 'TO_BASE64' => [[511, 1, 1, 'TP']], 'TO_DAYS' => [[511, 1, 1, 'IY']],
        'TO_SECONDS' => [[511, 1, 1, 'IY']], 'TO_VECTOR' => [[384, 1, 1, 'VY']], 'UCASE' => [[511, 1, 1, 'SP']], 'UNCOMPRESS' => [[511, 1, 1, 'BY']],
        'UNCOMPRESSED_LENGTH' => [[511, 1, 1, 'IP']], 'UNHEX' => [[511, 1, 1, 'BY']], 'UNIX_TIMESTAMP' => [[511, 0, 0, 'IN'], [511, 1, 1, 'WY']],
        'UPDATEXML' => [[511, 3, 3, 'SY']], 'UPPER' => [[511, 1, 1, 'SP']], 'UUID' => [[511, 0, 0, 'TN']], 'UUID_SHORT' => [[511, 0, 0, 'UN']],
        'UUID_TO_BIN' => [[508, 1, 2, 'BY']], 'VALIDATE_PASSWORD_STRENGTH' => [[511, 1, 1, 'IY']], 'VECTOR_DIM' => [[384, 1, 1, 'IY']],
        'VECTOR_TO_STRING' => [[384, 1, 1, 'TY']], 'VERSION' => [[511, 0, 0, 'TN']], 'WAIT_FOR_EXECUTED_GTID_SET' => [[510, 1, 2, 'IY']],
        'WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS' => [[1, 1, 2, 'IY'], [14, 1, 3, 'IY']], 'WEEKDAY' => [[511, 1, 1, 'IY']], 'WEEKOFYEAR' => [[511, 1, 1, 'IY']],
        'YEARWEEK' => [[511, 1, 2, 'IY']],
    ];
}
