<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Keyword;

/**
 * The productions of `unreserved_keyword`: the keywords that may be used as any name.
 *
 * Rule: PG-KEYWORD-NAME-001 (table). The list is the union of the shipped
 * grammar releases; each production reads one keyword terminal as a name.
 * Source: https://www.postgresql.org/docs/17/sql-keywords-appendix.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class UnreservedKeywords
{
    /**
     * The production signatures, one per keyword.
     */
    public const SIGNATURES = [
        'unreserved_keyword: ABORT_P', 'unreserved_keyword: ABSENT', 'unreserved_keyword: ABSOLUTE_P', 'unreserved_keyword: ACCESS',
        'unreserved_keyword: ACTION', 'unreserved_keyword: ADD_P', 'unreserved_keyword: ADMIN', 'unreserved_keyword: AFTER',
        'unreserved_keyword: AGGREGATE', 'unreserved_keyword: ALSO', 'unreserved_keyword: ALTER', 'unreserved_keyword: ALWAYS',
        'unreserved_keyword: ASENSITIVE', 'unreserved_keyword: ASSERTION', 'unreserved_keyword: ASSIGNMENT', 'unreserved_keyword: AT',
        'unreserved_keyword: ATOMIC', 'unreserved_keyword: ATTACH', 'unreserved_keyword: ATTRIBUTE', 'unreserved_keyword: BACKWARD',
        'unreserved_keyword: BEFORE', 'unreserved_keyword: BEGIN_P', 'unreserved_keyword: BREADTH', 'unreserved_keyword: BY',
        'unreserved_keyword: CACHE', 'unreserved_keyword: CALL', 'unreserved_keyword: CALLED', 'unreserved_keyword: CASCADE',
        'unreserved_keyword: CASCADED', 'unreserved_keyword: CATALOG_P', 'unreserved_keyword: CHAIN', 'unreserved_keyword: CHARACTERISTICS',
        'unreserved_keyword: CHECKPOINT', 'unreserved_keyword: CLASS', 'unreserved_keyword: CLOSE', 'unreserved_keyword: CLUSTER',
        'unreserved_keyword: COLUMNS', 'unreserved_keyword: COMMENT', 'unreserved_keyword: COMMENTS', 'unreserved_keyword: COMMIT',
        'unreserved_keyword: COMMITTED', 'unreserved_keyword: COMPRESSION', 'unreserved_keyword: CONDITIONAL', 'unreserved_keyword: CONFIGURATION',
        'unreserved_keyword: CONFLICT', 'unreserved_keyword: CONNECTION', 'unreserved_keyword: CONSTRAINTS', 'unreserved_keyword: CONTENT_P',
        'unreserved_keyword: CONTINUE_P', 'unreserved_keyword: CONVERSION_P', 'unreserved_keyword: COPY', 'unreserved_keyword: COST',
        'unreserved_keyword: CSV', 'unreserved_keyword: CUBE', 'unreserved_keyword: CURRENT_P', 'unreserved_keyword: CURSOR',
        'unreserved_keyword: CYCLE', 'unreserved_keyword: DATABASE', 'unreserved_keyword: DATA_P', 'unreserved_keyword: DAY_P',
        'unreserved_keyword: DEALLOCATE', 'unreserved_keyword: DECLARE', 'unreserved_keyword: DEFAULTS', 'unreserved_keyword: DEFERRED',
        'unreserved_keyword: DEFINER', 'unreserved_keyword: DELETE_P', 'unreserved_keyword: DELIMITER', 'unreserved_keyword: DELIMITERS',
        'unreserved_keyword: DEPENDS', 'unreserved_keyword: DEPTH', 'unreserved_keyword: DETACH', 'unreserved_keyword: DICTIONARY',
        'unreserved_keyword: DISABLE_P', 'unreserved_keyword: DISCARD', 'unreserved_keyword: DOCUMENT_P', 'unreserved_keyword: DOMAIN_P',
        'unreserved_keyword: DOUBLE_P', 'unreserved_keyword: DROP', 'unreserved_keyword: EACH', 'unreserved_keyword: EMPTY_P',
        'unreserved_keyword: ENABLE_P', 'unreserved_keyword: ENCODING', 'unreserved_keyword: ENCRYPTED', 'unreserved_keyword: ENUM_P',
        'unreserved_keyword: ERROR_P', 'unreserved_keyword: ESCAPE', 'unreserved_keyword: EVENT', 'unreserved_keyword: EXCLUDE',
        'unreserved_keyword: EXCLUDING', 'unreserved_keyword: EXCLUSIVE', 'unreserved_keyword: EXECUTE', 'unreserved_keyword: EXPLAIN',
        'unreserved_keyword: EXPRESSION', 'unreserved_keyword: EXTENSION', 'unreserved_keyword: EXTERNAL', 'unreserved_keyword: FAMILY',
        'unreserved_keyword: FILTER', 'unreserved_keyword: FINALIZE', 'unreserved_keyword: FIRST_P', 'unreserved_keyword: FOLLOWING',
        'unreserved_keyword: FORCE', 'unreserved_keyword: FORMAT', 'unreserved_keyword: FORWARD', 'unreserved_keyword: FUNCTION',
        'unreserved_keyword: FUNCTIONS', 'unreserved_keyword: GENERATED', 'unreserved_keyword: GLOBAL', 'unreserved_keyword: GRANTED',
        'unreserved_keyword: GROUPS', 'unreserved_keyword: HANDLER', 'unreserved_keyword: HEADER_P', 'unreserved_keyword: HOLD',
        'unreserved_keyword: HOUR_P', 'unreserved_keyword: IDENTITY_P', 'unreserved_keyword: IF_P', 'unreserved_keyword: IMMEDIATE',
        'unreserved_keyword: IMMUTABLE', 'unreserved_keyword: IMPLICIT_P', 'unreserved_keyword: IMPORT_P', 'unreserved_keyword: INCLUDE',
        'unreserved_keyword: INCLUDING', 'unreserved_keyword: INCREMENT', 'unreserved_keyword: INDENT', 'unreserved_keyword: INDEX',
        'unreserved_keyword: INDEXES', 'unreserved_keyword: INHERIT', 'unreserved_keyword: INHERITS', 'unreserved_keyword: INLINE_P',
        'unreserved_keyword: INPUT_P', 'unreserved_keyword: INSENSITIVE', 'unreserved_keyword: INSERT', 'unreserved_keyword: INSTEAD',
        'unreserved_keyword: INVOKER', 'unreserved_keyword: ISOLATION', 'unreserved_keyword: JSON', 'unreserved_keyword: KEEP',
        'unreserved_keyword: KEY', 'unreserved_keyword: KEYS', 'unreserved_keyword: LABEL', 'unreserved_keyword: LANGUAGE',
        'unreserved_keyword: LARGE_P', 'unreserved_keyword: LAST_P', 'unreserved_keyword: LEAKPROOF', 'unreserved_keyword: LEVEL',
        'unreserved_keyword: LISTEN', 'unreserved_keyword: LOAD', 'unreserved_keyword: LOCAL', 'unreserved_keyword: LOCATION',
        'unreserved_keyword: LOCKED', 'unreserved_keyword: LOCK_P', 'unreserved_keyword: LOGGED', 'unreserved_keyword: MAPPING',
        'unreserved_keyword: MATCH', 'unreserved_keyword: MATCHED', 'unreserved_keyword: MATERIALIZED', 'unreserved_keyword: MAXVALUE',
        'unreserved_keyword: MERGE', 'unreserved_keyword: METHOD', 'unreserved_keyword: MINUTE_P', 'unreserved_keyword: MINVALUE',
        'unreserved_keyword: MODE', 'unreserved_keyword: MONTH_P', 'unreserved_keyword: MOVE', 'unreserved_keyword: NAMES',
        'unreserved_keyword: NAME_P', 'unreserved_keyword: NESTED', 'unreserved_keyword: NEW', 'unreserved_keyword: NEXT',
        'unreserved_keyword: NFC', 'unreserved_keyword: NFD', 'unreserved_keyword: NFKC', 'unreserved_keyword: NFKD',
        'unreserved_keyword: NO', 'unreserved_keyword: NORMALIZED', 'unreserved_keyword: NOTHING', 'unreserved_keyword: NOTIFY',
        'unreserved_keyword: NOWAIT', 'unreserved_keyword: NULLS_P', 'unreserved_keyword: OBJECT_P', 'unreserved_keyword: OF',
        'unreserved_keyword: OFF', 'unreserved_keyword: OIDS', 'unreserved_keyword: OLD', 'unreserved_keyword: OMIT',
        'unreserved_keyword: OPERATOR', 'unreserved_keyword: OPTION', 'unreserved_keyword: OPTIONS', 'unreserved_keyword: ORDINALITY',
        'unreserved_keyword: OTHERS', 'unreserved_keyword: OVER', 'unreserved_keyword: OVERRIDING', 'unreserved_keyword: OWNED',
        'unreserved_keyword: OWNER', 'unreserved_keyword: PARALLEL', 'unreserved_keyword: PARAMETER', 'unreserved_keyword: PARSER',
        'unreserved_keyword: PARTIAL', 'unreserved_keyword: PARTITION', 'unreserved_keyword: PASSING', 'unreserved_keyword: PASSWORD',
        'unreserved_keyword: PATH', 'unreserved_keyword: PLAN', 'unreserved_keyword: PLANS', 'unreserved_keyword: POLICY',
        'unreserved_keyword: PRECEDING', 'unreserved_keyword: PREPARE', 'unreserved_keyword: PREPARED', 'unreserved_keyword: PRESERVE',
        'unreserved_keyword: PRIOR', 'unreserved_keyword: PRIVILEGES', 'unreserved_keyword: PROCEDURAL', 'unreserved_keyword: PROCEDURE',
        'unreserved_keyword: PROCEDURES', 'unreserved_keyword: PROGRAM', 'unreserved_keyword: PUBLICATION', 'unreserved_keyword: QUOTE',
        'unreserved_keyword: QUOTES', 'unreserved_keyword: RANGE', 'unreserved_keyword: READ', 'unreserved_keyword: REASSIGN',
        'unreserved_keyword: RECHECK', 'unreserved_keyword: RECURSIVE', 'unreserved_keyword: REFERENCING', 'unreserved_keyword: REFRESH',
        'unreserved_keyword: REF_P', 'unreserved_keyword: REINDEX', 'unreserved_keyword: RELATIVE_P', 'unreserved_keyword: RELEASE',
        'unreserved_keyword: RENAME', 'unreserved_keyword: REPEATABLE', 'unreserved_keyword: REPLACE', 'unreserved_keyword: REPLICA',
        'unreserved_keyword: RESET', 'unreserved_keyword: RESTART', 'unreserved_keyword: RESTRICT', 'unreserved_keyword: RETURN',
        'unreserved_keyword: RETURNS', 'unreserved_keyword: REVOKE', 'unreserved_keyword: ROLE', 'unreserved_keyword: ROLLBACK',
        'unreserved_keyword: ROLLUP', 'unreserved_keyword: ROUTINE', 'unreserved_keyword: ROUTINES', 'unreserved_keyword: ROWS',
        'unreserved_keyword: RULE', 'unreserved_keyword: SAVEPOINT', 'unreserved_keyword: SCALAR', 'unreserved_keyword: SCHEMA',
        'unreserved_keyword: SCHEMAS', 'unreserved_keyword: SCROLL', 'unreserved_keyword: SEARCH', 'unreserved_keyword: SECOND_P',
        'unreserved_keyword: SECURITY', 'unreserved_keyword: SEQUENCE', 'unreserved_keyword: SEQUENCES', 'unreserved_keyword: SERIALIZABLE',
        'unreserved_keyword: SERVER', 'unreserved_keyword: SESSION', 'unreserved_keyword: SET', 'unreserved_keyword: SETS',
        'unreserved_keyword: SHARE', 'unreserved_keyword: SHOW', 'unreserved_keyword: SIMPLE', 'unreserved_keyword: SKIP',
        'unreserved_keyword: SNAPSHOT', 'unreserved_keyword: SOURCE', 'unreserved_keyword: SQL_P', 'unreserved_keyword: STABLE',
        'unreserved_keyword: STANDALONE_P', 'unreserved_keyword: START', 'unreserved_keyword: STATEMENT', 'unreserved_keyword: STATISTICS',
        'unreserved_keyword: STDIN', 'unreserved_keyword: STDOUT', 'unreserved_keyword: STORAGE', 'unreserved_keyword: STORED',
        'unreserved_keyword: STRICT_P', 'unreserved_keyword: STRING_P', 'unreserved_keyword: STRIP_P', 'unreserved_keyword: SUBSCRIPTION',
        'unreserved_keyword: SUPPORT', 'unreserved_keyword: SYSID', 'unreserved_keyword: SYSTEM_P', 'unreserved_keyword: TABLES',
        'unreserved_keyword: TABLESPACE', 'unreserved_keyword: TARGET', 'unreserved_keyword: TEMP', 'unreserved_keyword: TEMPLATE',
        'unreserved_keyword: TEMPORARY', 'unreserved_keyword: TEXT_P', 'unreserved_keyword: TIES', 'unreserved_keyword: TRANSACTION',
        'unreserved_keyword: TRANSFORM', 'unreserved_keyword: TRIGGER', 'unreserved_keyword: TRUNCATE', 'unreserved_keyword: TRUSTED',
        'unreserved_keyword: TYPES_P', 'unreserved_keyword: TYPE_P', 'unreserved_keyword: UESCAPE', 'unreserved_keyword: UNBOUNDED',
        'unreserved_keyword: UNCOMMITTED', 'unreserved_keyword: UNCONDITIONAL', 'unreserved_keyword: UNENCRYPTED', 'unreserved_keyword: UNKNOWN',
        'unreserved_keyword: UNLISTEN', 'unreserved_keyword: UNLOGGED', 'unreserved_keyword: UNTIL', 'unreserved_keyword: UPDATE',
        'unreserved_keyword: VACUUM', 'unreserved_keyword: VALID', 'unreserved_keyword: VALIDATE', 'unreserved_keyword: VALIDATOR',
        'unreserved_keyword: VALUE_P', 'unreserved_keyword: VARYING', 'unreserved_keyword: VERSION_P', 'unreserved_keyword: VIEW',
        'unreserved_keyword: VIEWS', 'unreserved_keyword: VOLATILE', 'unreserved_keyword: WHITESPACE_P', 'unreserved_keyword: WITHIN',
        'unreserved_keyword: WITHOUT', 'unreserved_keyword: WORK', 'unreserved_keyword: WRAPPER', 'unreserved_keyword: WRITE',
        'unreserved_keyword: XML_P', 'unreserved_keyword: YEAR_P', 'unreserved_keyword: YES_P', 'unreserved_keyword: ZONE',
    ];
}
