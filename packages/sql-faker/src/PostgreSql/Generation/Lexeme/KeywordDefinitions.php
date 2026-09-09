<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator;
use SqlFaker\Grammar\Generation\Version\VersionCase;
use SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator;

/**
 * Explicit keyword handlers from src/include/parser/kwlist.h; spellings remain in the upstream table.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/include/parser/kwlist.h
 */
final class KeywordDefinitions
{
    private const TERMINALS = [
        'ABORT_P', 'ABSENT', 'ABSOLUTE_P', 'ACCESS', 'ACTION',
        'ADD_P', 'ADMIN', 'AFTER', 'AGGREGATE', 'ALL',
        'ALSO', 'ALTER', 'ALWAYS', 'ANALYSE', 'ANALYZE',
        'AND', 'ANY', 'ARRAY', 'AS', 'ASC',
        'ASENSITIVE', 'ASSERTION', 'ASSIGNMENT', 'ASYMMETRIC', 'AT',
        'ATOMIC', 'ATTACH', 'ATTRIBUTE', 'AUTHORIZATION', 'BACKWARD',
        'BEFORE', 'BEGIN_P', 'BETWEEN', 'BIGINT', 'BINARY',
        'BIT', 'BOOLEAN_P', 'BOTH', 'BREADTH', 'BY',
        'CACHE', 'CALL', 'CALLED', 'CASCADE', 'CASCADED',
        'CASE', 'CAST', 'CATALOG_P', 'CHAIN', 'CHARACTER',
        'CHARACTERISTICS', 'CHAR_P', 'CHECK', 'CHECKPOINT', 'CLASS',
        'CLOSE', 'CLUSTER', 'COALESCE', 'COLLATE', 'COLLATION',
        'COLUMN', 'COLUMNS', 'COMMENT', 'COMMENTS', 'COMMIT',
        'COMMITTED', 'COMPRESSION', 'CONCURRENTLY', 'CONDITIONAL', 'CONFIGURATION',
        'CONFLICT', 'CONNECTION', 'CONSTRAINT', 'CONSTRAINTS', 'CONTENT_P',
        'CONTINUE_P', 'CONVERSION_P', 'COPY', 'COST', 'CREATE',
        'CROSS', 'CSV', 'CUBE', 'CURRENT_CATALOG', 'CURRENT_DATE',
        'CURRENT_P', 'CURRENT_ROLE', 'CURRENT_SCHEMA', 'CURRENT_TIME', 'CURRENT_TIMESTAMP',
        'CURRENT_USER', 'CURSOR', 'CYCLE', 'DATABASE', 'DATA_P',
        'DAY_P', 'DEALLOCATE', 'DEC', 'DECIMAL_P', 'DECLARE',
        'DEFAULT', 'DEFAULTS', 'DEFERRABLE', 'DEFERRED', 'DEFINER',
        'DELETE_P', 'DELIMITER', 'DELIMITERS', 'DEPENDS', 'DEPTH',
        'DESC', 'DETACH', 'DICTIONARY', 'DISABLE_P', 'DISCARD',
        'DISTINCT', 'DO', 'DOCUMENT_P', 'DOMAIN_P', 'DOUBLE_P',
        'DROP', 'EACH', 'ELSE', 'EMPTY_P', 'ENABLE_P',
        'ENCODING', 'ENCRYPTED', 'END_P', 'ENUM_P', 'ERROR_P',
        'ESCAPE', 'EVENT', 'EXCEPT', 'EXCLUDE', 'EXCLUDING',
        'EXCLUSIVE', 'EXECUTE', 'EXISTS', 'EXPLAIN', 'EXPRESSION',
        'EXTENSION', 'EXTERNAL', 'EXTRACT', 'FALSE_P', 'FAMILY',
        'FETCH', 'FILTER', 'FINALIZE', 'FIRST_P', 'FLOAT_P',
        'FOLLOWING', 'FOR', 'FORCE', 'FOREIGN', 'FORMAT',
        'FORWARD', 'FREEZE', 'FROM', 'FULL', 'FUNCTION',
        'FUNCTIONS', 'GENERATED', 'GLOBAL', 'GRANT', 'GRANTED',
        'GREATEST', 'GROUPING', 'GROUPS', 'GROUP_P', 'HANDLER',
        'HAVING', 'HEADER_P', 'HOLD', 'HOUR_P', 'IDENTITY_P',
        'IF_P', 'ILIKE', 'IMMEDIATE', 'IMMUTABLE', 'IMPLICIT_P',
        'IMPORT_P', 'INCLUDE', 'INCLUDING', 'INCREMENT', 'INDENT',
        'INDEX', 'INDEXES', 'INHERIT', 'INHERITS', 'INITIALLY',
        'INLINE_P', 'INNER_P', 'INOUT', 'INPUT_P', 'INSENSITIVE',
        'INSERT', 'INSTEAD', 'INTEGER', 'INTERSECT', 'INTERVAL',
        'INTO', 'INT_P', 'INVOKER', 'IN_P', 'IS',
        'ISNULL', 'ISOLATION', 'JOIN', 'JSON', 'JSON_ARRAY',
        'JSON_ARRAYAGG', 'JSON_EXISTS', 'JSON_OBJECT', 'JSON_OBJECTAGG', 'JSON_QUERY',
        'JSON_SCALAR', 'JSON_SERIALIZE', 'JSON_TABLE', 'JSON_VALUE', 'KEEP',
        'KEY', 'KEYS', 'LABEL', 'LANGUAGE', 'LARGE_P',
        'LAST_P', 'LATERAL_P', 'LEADING', 'LEAKPROOF', 'LEAST',
        'LEFT', 'LEVEL', 'LIKE', 'LIMIT', 'LISTEN',
        'LOAD', 'LOCAL', 'LOCALTIME', 'LOCALTIMESTAMP', 'LOCATION',
        'LOCKED', 'LOCK_P', 'LOGGED', 'MAPPING', 'MATCH',
        'MATCHED', 'MATERIALIZED', 'MAXVALUE', 'MERGE', 'MERGE_ACTION',
        'METHOD', 'MINUTE_P', 'MINVALUE', 'MODE', 'MONTH_P',
        'MOVE', 'NAMES', 'NAME_P', 'NATIONAL', 'NATURAL',
        'NCHAR', 'NESTED', 'NEW', 'NEXT', 'NFC',
        'NFD', 'NFKC', 'NFKD', 'NO', 'NONE',
        'NORMALIZE', 'NORMALIZED', 'NOT', 'NOTHING', 'NOTIFY',
        'NOTNULL', 'NOWAIT', 'NULLIF', 'NULLS_P', 'NULL_P',
        'NUMERIC', 'OBJECT_P', 'OF', 'OFF', 'OFFSET',
        'OIDS', 'OLD', 'OMIT', 'ON', 'ONLY',
        'OPERATOR', 'OPTION', 'OPTIONS', 'OR', 'ORDER',
        'ORDINALITY', 'OTHERS', 'OUTER_P', 'OUT_P', 'OVER',
        'OVERLAPS', 'OVERLAY', 'OVERRIDING', 'OWNED', 'OWNER',
        'PARALLEL', 'PARAMETER', 'PARSER', 'PARTIAL', 'PARTITION',
        'PASSING', 'PASSWORD', 'PATH', 'PLACING', 'PLAN',
        'PLANS', 'POLICY', 'POSITION', 'PRECEDING', 'PRECISION',
        'PREPARE', 'PREPARED', 'PRESERVE', 'PRIMARY', 'PRIOR',
        'PRIVILEGES', 'PROCEDURAL', 'PROCEDURE', 'PROCEDURES', 'PROGRAM',
        'PUBLICATION', 'QUOTE', 'QUOTES', 'RANGE', 'READ',
        'REAL', 'REASSIGN', 'RECHECK', 'RECURSIVE', 'REFERENCES',
        'REFERENCING', 'REFRESH', 'REF_P', 'REINDEX', 'RELATIVE_P',
        'RELEASE', 'RENAME', 'REPEATABLE', 'REPLACE', 'REPLICA',
        'RESET', 'RESTART', 'RESTRICT', 'RETURN', 'RETURNING',
        'RETURNS', 'REVOKE', 'RIGHT', 'ROLE', 'ROLLBACK',
        'ROLLUP', 'ROUTINE', 'ROUTINES', 'ROW', 'ROWS',
        'RULE', 'SAVEPOINT', 'SCALAR', 'SCHEMA', 'SCHEMAS',
        'SCROLL', 'SEARCH', 'SECOND_P', 'SECURITY', 'SELECT',
        'SEQUENCE', 'SEQUENCES', 'SERIALIZABLE', 'SERVER', 'SESSION',
        'SESSION_USER', 'SET', 'SETOF', 'SETS', 'SHARE',
        'SHOW', 'SIMILAR', 'SIMPLE', 'SKIP', 'SMALLINT',
        'SNAPSHOT', 'SOME', 'SOURCE', 'SQL_P', 'STABLE',
        'STANDALONE_P', 'START', 'STATEMENT', 'STATISTICS', 'STDIN',
        'STDOUT', 'STORAGE', 'STORED', 'STRICT_P', 'STRING_P',
        'STRIP_P', 'SUBSCRIPTION', 'SUBSTRING', 'SUPPORT', 'SYMMETRIC',
        'SYSID', 'SYSTEM_P', 'SYSTEM_USER', 'TABLE', 'TABLES',
        'TABLESAMPLE', 'TABLESPACE', 'TARGET', 'TEMP', 'TEMPLATE',
        'TEMPORARY', 'TEXT_P', 'THEN', 'TIES', 'TIME',
        'TIMESTAMP', 'TO', 'TRAILING', 'TRANSACTION', 'TRANSFORM',
        'TREAT', 'TRIGGER', 'TRIM', 'TRUE_P', 'TRUNCATE',
        'TRUSTED', 'TYPES_P', 'TYPE_P', 'UESCAPE', 'UNBOUNDED',
        'UNCOMMITTED', 'UNCONDITIONAL', 'UNENCRYPTED', 'UNION', 'UNIQUE',
        'UNKNOWN', 'UNLISTEN', 'UNLOGGED', 'UNTIL', 'UPDATE',
        'USER', 'USING', 'VACUUM', 'VALID', 'VALIDATE',
        'VALIDATOR', 'VALUES', 'VALUE_P', 'VARCHAR', 'VARIADIC',
        'VARYING', 'VERBOSE', 'VERSION_P', 'VIEW', 'VIEWS',
        'VOLATILE', 'WHEN', 'WHERE', 'WHITESPACE_P', 'WINDOW',
        'WITH', 'WITHIN', 'WITHOUT', 'WORK', 'WRAPPER',
        'WRITE', 'XMLATTRIBUTES', 'XMLCONCAT', 'XMLELEMENT', 'XMLEXISTS',
        'XMLFOREST', 'XMLNAMESPACES', 'XMLPARSE', 'XMLPI', 'XMLROOT',
        'XMLSERIALIZE', 'XMLTABLE', 'XML_P', 'YEAR_P', 'YES_P',
        'ZONE', 'FORMAT_LA', 'NOT_LA', 'NULLS_LA', 'WITH_LA',
        'WITHOUT_LA',
    ];

    /**
     * Binds the reviewed keyword dispatch to one exact release.
     * @param array<string, list<string>> $keywords
     */
    public function create(string $version, array $keywords): LexemeGenerator
    {
        return new VersionedLexemeGenerator($version, new VersionCase(
            ['pg-17.2'],
            new MatchingLexemeGenerator(
                static fn (LexemeInput $input): bool => in_array($input->terminal()->name, self::TERMINALS, true),
                new KeywordLexemeGenerator($keywords),
            ),
            'pg-17.2-keywords',
        ));
    }
}
