<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Lexeme;

use RuntimeException;
use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\CandidateResolver;
use SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\ChoiceDomain;
use SqlFaker\Grammar\Generation\Value\DollarQuotedDomain;
use SqlFaker\Grammar\Generation\Value\IdentifierDomain;
use SqlFaker\Grammar\Generation\Value\OperatorDomain;
use SqlFaker\Grammar\Generation\Value\QuotedDomain;
use SqlFaker\Grammar\Generation\Value\SequenceDomain;
use SqlFaker\Grammar\Generation\Value\WordDomain;
use SqlFaker\Grammar\Generation\Version\VersionCase;
use SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator;
use SqlFaker\Grammar\SqlVersion;

/**
 * Composes REL_17_2 scan.l domains and parser.c selector/lookahead tokens.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/parser.c
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/scan.l
 */
final class DefinitionFactory
{
    /**
     * Composes the selected release directly from the declarations in this file.
     * @throws RuntimeException When the exact release is unsupported
     */
    public function create(string $version): ReverseLexemeGenerator
    {
        return new ReverseLexemeGenerator($this->lexemes($version), new CandidateResolver(new CombinedSpacingRule()), $version, 'PostgreSQL');
    }

    /**
     * Composes the selected release directly from the declarations in this file.
     * @throws RuntimeException When the exact release is unsupported
     */
    public function lexemes(string $version): LexemeGenerator
    {
        $keywords = $this->keywords($version);
        return new ChoiceLexemeGenerator(
            $this->keywordLexemes($version, $keywords),
            new VersionedLexemeGenerator($version, new VersionCase(['pg-17.2'], new ChoiceLexemeGenerator(
                $this->values(),
                $this->contextualNames(),
                new HashBoundLexemeGenerator(),
                $this->symbols(),
            ), 'pg-17.2-scanner')),
        );
    }

    /**
     * Declares scan.l self tokens and fixed operators, plus parser.c's non-output selector tokens.
     */
    public function symbols(): LexemeGenerator
    {
        $generators = [];
        foreach (str_split('(),;[]:+-*/%^<>=.') as $character) {
            $generators[] = new MatchingLexemeGenerator($character, new FixedLexemeGenerator($character, 'symbol', 'scan.l:self'));
        }
        $fixed = ['TYPECAST' => '::', 'DOT_DOT' => '..', 'COLON_EQUALS' => ':=', 'EQUALS_GREATER' => '=>',
            'NOT_EQUALS' => '<>', 'LESS_EQUALS' => '<=', 'GREATER_EQUALS' => '>='];
        foreach ($fixed as $terminal => $text) {
            $generators[] = new MatchingLexemeGenerator($terminal, new FixedLexemeGenerator($text, 'symbol', 'scan.l/parser.c:' . $terminal));
        }
        foreach ($this->nonOutput() as $terminal) {
            $generators[] = new MatchingLexemeGenerator($terminal, new FixedLexemeGenerator('', 'marker', 'parser.c:base_yylex'));
        }
        return new ChoiceLexemeGenerator(...$generators);
    }

    /**
     * Supplies non-output selector declarations to both lexical realization and token budgets.
     * @return list<string>
     */
    public function nonOutput(): array
    {
        return ['MODE_TYPE_NAME', 'MODE_PLPGSQL_EXPR', 'MODE_PLPGSQL_ASSIGN1', 'MODE_PLPGSQL_ASSIGN2', 'MODE_PLPGSQL_ASSIGN3'];
    }

    /**
     * scan.l value states and gram.y contextual numbers.
     */
    public function values(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator($this->names(), $this->strings(), $this->numbers());
    }

    /**
     * identifier / xd / xui / param, with quoted values kept inside their lexeme.
     * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/scan.l
     */
    public function names(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            new ValueLexemeGenerator('IDENT', new ChoiceDomain(new IdentifierDomain('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$', '_sf', 62), new QuotedDomain('"', minimum: 1, maximum: 63)), ['_sqlfaker_identifier'], 'identifier', 'scan.l:identifier/xd'),
            new ValueLexemeGenerator('UIDENT', new QuotedDomain('"', ['U&', 'u&'], minimum: 1, maximum: 63, unicodeEscapes: true), ['U&"name"'], 'quoted-identifier', 'scan.l:xui'),
            new ValueLexemeGenerator('PARAM', new SequenceDomain(new WordDomain(['$']), new CharacterDomain(str_split('123456789'), 1, 1), new CharacterDomain(str_split('0123456789'), 0, 4)), ['$1'], 'parameter', 'scan.l:param'),
        );
    }

    /**
     * xq / xe / xdolq / xus / xb / xh under standard_conforming_strings.
     * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/scan.l
     * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
     */
    public function strings(): LexemeGenerator
    {
        $characters = [...array_map(chr(...), range(1, 127)), 'é', '猫', '😀'];
        $plain = new QuotedDomain("'", alphabet: $characters);
        $dollar = new DollarQuotedDomain(
            new ChoiceDomain(new WordDomain(['']), new IdentifierDomain('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_', '_', 17)),
            new CharacterDomain([...array_map(chr(...), [...range(1, 35), ...range(37, 127)]), 'é', '猫', '😀'], 0, 255),
        );
        return new ChoiceLexemeGenerator(
            new ValueLexemeGenerator('JSON_TABLE_PATH', new WordDomain(["'$'", "'$[*]'", "'$.a'", "'$.name'"]), ["'$'", "'$[*]'"], 'string', 'gram.y:json_table:string-path'),
            new ValueLexemeGenerator('SCONST', new ChoiceDomain($plain, new QuotedDomain("'", ['E', 'e'], true, alphabet: $characters), $dollar), ["'text'", "'a''b'", '$$text$$'], 'string', 'scan.l:xq/xe/xdolq'),
            new ValueLexemeGenerator('USCONST', new QuotedDomain("'", ['U&', 'u&'], alphabet: $characters, unicodeEscapes: true), ["U&'text'"], 'string', 'scan.l:xus'),
            new ValueLexemeGenerator('BCONST', new SequenceDomain(new WordDomain(['B', 'b']), new CharacterDomain(['0', '1'], 0, 255, "'", "'")), ["B'01'"], 'string', 'scan.l:xb'),
            new ValueLexemeGenerator('XCONST', new SequenceDomain(new WordDomain(['X', 'x']), new CharacterDomain(str_split('0123456789abcdefABCDEF'), 0, 255, "'", "'")), ["X'0f'"], 'string', 'scan.l:xh'),
        );
    }

    /**
     * process_integer_literal, numeric / real and operator scanner rules.
     * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/scan.l
     * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
     */
    public function numbers(): LexemeGenerator
    {
        $digits = new CharacterDomain(str_split('0123456789'), 1, 20);
        $decimal = new ChoiceDomain(new SequenceDomain($digits, new WordDomain(['.']), new CharacterDomain(str_split('0123456789'), 0, 30)), new SequenceDomain(new WordDomain(['.']), $digits));
        $exponent = new SequenceDomain(new WordDomain(['e', 'E']), new WordDomain(['', '+', '-']), new CharacterDomain(str_split('0123456789'), 1, 3));
        return new ChoiceLexemeGenerator(
            new IntegerLexemeGenerator('FLOAT_PRECISION_NUMBER', '1', '53', ['1', '24', '53'], 'gram.y:opt_float', true),
            new IntegerLexemeGenerator('COLUMN_POSITION_NUMBER', '1', '32767', ['1', '32767'], 'gram.y:alter_table_cmd:column-number', true),
            new IntegerLexemeGenerator('ICONST', '0', '2147483647', ['1', '0', '2', '1_0'], 'scan.l:process_integer_literal:ICONST', true),
            new IntegerLexemeGenerator('FCONST', '2147483648', null, ['2147483648'], 'scan.l:process_integer_literal:FCONST', true),
            new ValueLexemeGenerator('FCONST', new ChoiceDomain($decimal, new SequenceDomain(new ChoiceDomain($digits, $decimal), $exponent)), ['1.5', '.5', '1e2'], 'number', 'scan.l:numeric/real'),
            new ValueLexemeGenerator('Op', new OperatorDomain('+*/<>=!@#%^&|`?~-', '~!@#^&|`?%', ['+', '-', '*', '/', '%', '^', '<', '>', '=', '>=', '<=', '=>', '<>', '!='], ['--', '/*']), ['?', '?|', '?&'], 'operator', 'scan.l:operator'),
        );
    }
    /**
     * Binds the reviewed keyword dispatch to one exact release.
     * @param array<string, list<string>> $keywords
     */
    public function keywordLexemes(string $version, array $keywords): LexemeGenerator
    {
        return new VersionedLexemeGenerator($version, new VersionCase(
            ['pg-17.2'],
            new MatchingLexemeGenerator(
                static fn (LexemeInput $input): bool => (isset($keywords[$input->terminal()->name]) || in_array($input->terminal()->name, ['FORMAT_LA', 'NOT_LA', 'NULLS_LA', 'WITH_LA', 'WITHOUT_LA'], true)),
                new KeywordLexemeGenerator($keywords),
            ),
            'pg-17.2-keywords',
        ));
    }

    /**
     * Composes each source-defined domain after its grammar position has been identified by rewriting.
     */
    public function contextualNames(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            $this->contextualWord('PARTITION_STRATEGY', ['LIST', 'RANGE', 'HASH'], 'parsePartitionStrategy'),
            $this->contextualWord('JSON_ENCODING', ['UTF8', 'UTF16', 'UTF32'], 'json_format_clause'),
            $this->contextualWord('POLICY_MODE', ['PERMISSIVE', 'RESTRICTIVE'], 'RowSecurityDefaultPermissive'),
            $this->contextualWord('ROLE_OPTION', [
                'SUPERUSER', 'NOSUPERUSER', 'CREATEROLE', 'NOCREATEROLE',
                'REPLICATION', 'NOREPLICATION', 'CREATEDB', 'NOCREATEDB',
                'LOGIN', 'NOLOGIN', 'BYPASSRLS', 'NOBYPASSRLS', 'NOINHERIT',
            ], 'AlterOptRoleElem'),
        );
    }

    /**
     * Keeps the complete declared word domain in one composable handler.
     * @param non-empty-list<string> $words
     */
    public function contextualWord(string $terminal, array $words, string $rule): LexemeGenerator
    {
        return new ValueLexemeGenerator($terminal, new WordDomain($words, true), $words, 'identifier', 'gram.y:' . $rule);
    }
    /**
     * Fixed spellings beside their parser terminals.
     * @see https://github.com/postgres/postgres/blob/REL_17_2/src/include/parser/kwlist.h
     * @throws RuntimeException When the exact release is unsupported
     * @return array<string, list<string>>
     */
    public function keywords(string $version): array
    {
        SqlVersion::resolve('postgresql', $version);
        return self::KEYWORDS;
    }

    /**
     * @var array<string, list<string>>
     */
    private const KEYWORDS = [
        'ABORT_P' => ['ABORT'],
        'ABSENT' => ['ABSENT'],
        'ABSOLUTE_P' => ['ABSOLUTE'],
        'ACCESS' => ['ACCESS'],
        'ACTION' => ['ACTION'],
        'ADD_P' => ['ADD'],
        'ADMIN' => ['ADMIN'],
        'AFTER' => ['AFTER'],
        'AGGREGATE' => ['AGGREGATE'],
        'ALL' => ['ALL'],
        'ALSO' => ['ALSO'],
        'ALTER' => ['ALTER'],
        'ALWAYS' => ['ALWAYS'],
        'ANALYSE' => ['ANALYSE'],
        'ANALYZE' => ['ANALYZE'],
        'AND' => ['AND'],
        'ANY' => ['ANY'],
        'ARRAY' => ['ARRAY'],
        'AS' => ['AS'],
        'ASC' => ['ASC'],
        'ASENSITIVE' => ['ASENSITIVE'],
        'ASSERTION' => ['ASSERTION'],
        'ASSIGNMENT' => ['ASSIGNMENT'],
        'ASYMMETRIC' => ['ASYMMETRIC'],
        'AT' => ['AT'],
        'ATOMIC' => ['ATOMIC'],
        'ATTACH' => ['ATTACH'],
        'ATTRIBUTE' => ['ATTRIBUTE'],
        'AUTHORIZATION' => ['AUTHORIZATION'],
        'BACKWARD' => ['BACKWARD'],
        'BEFORE' => ['BEFORE'],
        'BEGIN_P' => ['BEGIN'],
        'BETWEEN' => ['BETWEEN'],
        'BIGINT' => ['BIGINT'],
        'BINARY' => ['BINARY'],
        'BIT' => ['BIT'],
        'BOOLEAN_P' => ['BOOLEAN'],
        'BOTH' => ['BOTH'],
        'BREADTH' => ['BREADTH'],
        'BY' => ['BY'],
        'CACHE' => ['CACHE'],
        'CALL' => ['CALL'],
        'CALLED' => ['CALLED'],
        'CASCADE' => ['CASCADE'],
        'CASCADED' => ['CASCADED'],
        'CASE' => ['CASE'],
        'CAST' => ['CAST'],
        'CATALOG_P' => ['CATALOG'],
        'CHAIN' => ['CHAIN'],
        'CHARACTER' => ['CHARACTER'],
        'CHARACTERISTICS' => ['CHARACTERISTICS'],
        'CHAR_P' => ['CHAR'],
        'CHECK' => ['CHECK'],
        'CHECKPOINT' => ['CHECKPOINT'],
        'CLASS' => ['CLASS'],
        'CLOSE' => ['CLOSE'],
        'CLUSTER' => ['CLUSTER'],
        'COALESCE' => ['COALESCE'],
        'COLLATE' => ['COLLATE'],
        'COLLATION' => ['COLLATION'],
        'COLUMN' => ['COLUMN'],
        'COLUMNS' => ['COLUMNS'],
        'COMMENT' => ['COMMENT'],
        'COMMENTS' => ['COMMENTS'],
        'COMMIT' => ['COMMIT'],
        'COMMITTED' => ['COMMITTED'],
        'COMPRESSION' => ['COMPRESSION'],
        'CONCURRENTLY' => ['CONCURRENTLY'],
        'CONDITIONAL' => ['CONDITIONAL'],
        'CONFIGURATION' => ['CONFIGURATION'],
        'CONFLICT' => ['CONFLICT'],
        'CONNECTION' => ['CONNECTION'],
        'CONSTRAINT' => ['CONSTRAINT'],
        'CONSTRAINTS' => ['CONSTRAINTS'],
        'CONTENT_P' => ['CONTENT'],
        'CONTINUE_P' => ['CONTINUE'],
        'CONVERSION_P' => ['CONVERSION'],
        'COPY' => ['COPY'],
        'COST' => ['COST'],
        'CREATE' => ['CREATE'],
        'CROSS' => ['CROSS'],
        'CSV' => ['CSV'],
        'CUBE' => ['CUBE'],
        'CURRENT_CATALOG' => ['CURRENT_CATALOG'],
        'CURRENT_DATE' => ['CURRENT_DATE'],
        'CURRENT_P' => ['CURRENT'],
        'CURRENT_ROLE' => ['CURRENT_ROLE'],
        'CURRENT_SCHEMA' => ['CURRENT_SCHEMA'],
        'CURRENT_TIME' => ['CURRENT_TIME'],
        'CURRENT_TIMESTAMP' => ['CURRENT_TIMESTAMP'],
        'CURRENT_USER' => ['CURRENT_USER'],
        'CURSOR' => ['CURSOR'],
        'CYCLE' => ['CYCLE'],
        'DATABASE' => ['DATABASE'],
        'DATA_P' => ['DATA'],
        'DAY_P' => ['DAY'],
        'DEALLOCATE' => ['DEALLOCATE'],
        'DEC' => ['DEC'],
        'DECIMAL_P' => ['DECIMAL'],
        'DECLARE' => ['DECLARE'],
        'DEFAULT' => ['DEFAULT'],
        'DEFAULTS' => ['DEFAULTS'],
        'DEFERRABLE' => ['DEFERRABLE'],
        'DEFERRED' => ['DEFERRED'],
        'DEFINER' => ['DEFINER'],
        'DELETE_P' => ['DELETE'],
        'DELIMITER' => ['DELIMITER'],
        'DELIMITERS' => ['DELIMITERS'],
        'DEPENDS' => ['DEPENDS'],
        'DEPTH' => ['DEPTH'],
        'DESC' => ['DESC'],
        'DETACH' => ['DETACH'],
        'DICTIONARY' => ['DICTIONARY'],
        'DISABLE_P' => ['DISABLE'],
        'DISCARD' => ['DISCARD'],
        'DISTINCT' => ['DISTINCT'],
        'DO' => ['DO'],
        'DOCUMENT_P' => ['DOCUMENT'],
        'DOMAIN_P' => ['DOMAIN'],
        'DOUBLE_P' => ['DOUBLE'],
        'DROP' => ['DROP'],
        'EACH' => ['EACH'],
        'ELSE' => ['ELSE'],
        'EMPTY_P' => ['EMPTY'],
        'ENABLE_P' => ['ENABLE'],
        'ENCODING' => ['ENCODING'],
        'ENCRYPTED' => ['ENCRYPTED'],
        'END_P' => ['END'],
        'ENUM_P' => ['ENUM'],
        'ERROR_P' => ['ERROR'],
        'ESCAPE' => ['ESCAPE'],
        'EVENT' => ['EVENT'],
        'EXCEPT' => ['EXCEPT'],
        'EXCLUDE' => ['EXCLUDE'],
        'EXCLUDING' => ['EXCLUDING'],
        'EXCLUSIVE' => ['EXCLUSIVE'],
        'EXECUTE' => ['EXECUTE'],
        'EXISTS' => ['EXISTS'],
        'EXPLAIN' => ['EXPLAIN'],
        'EXPRESSION' => ['EXPRESSION'],
        'EXTENSION' => ['EXTENSION'],
        'EXTERNAL' => ['EXTERNAL'],
        'EXTRACT' => ['EXTRACT'],
        'FALSE_P' => ['FALSE'],
        'FAMILY' => ['FAMILY'],
        'FETCH' => ['FETCH'],
        'FILTER' => ['FILTER'],
        'FINALIZE' => ['FINALIZE'],
        'FIRST_P' => ['FIRST'],
        'FLOAT_P' => ['FLOAT'],
        'FOLLOWING' => ['FOLLOWING'],
        'FOR' => ['FOR'],
        'FORCE' => ['FORCE'],
        'FOREIGN' => ['FOREIGN'],
        'FORMAT' => ['FORMAT'],
        'FORWARD' => ['FORWARD'],
        'FREEZE' => ['FREEZE'],
        'FROM' => ['FROM'],
        'FULL' => ['FULL'],
        'FUNCTION' => ['FUNCTION'],
        'FUNCTIONS' => ['FUNCTIONS'],
        'GENERATED' => ['GENERATED'],
        'GLOBAL' => ['GLOBAL'],
        'GRANT' => ['GRANT'],
        'GRANTED' => ['GRANTED'],
        'GREATEST' => ['GREATEST'],
        'GROUPING' => ['GROUPING'],
        'GROUPS' => ['GROUPS'],
        'GROUP_P' => ['GROUP'],
        'HANDLER' => ['HANDLER'],
        'HAVING' => ['HAVING'],
        'HEADER_P' => ['HEADER'],
        'HOLD' => ['HOLD'],
        'HOUR_P' => ['HOUR'],
        'IDENTITY_P' => ['IDENTITY'],
        'IF_P' => ['IF'],
        'ILIKE' => ['ILIKE'],
        'IMMEDIATE' => ['IMMEDIATE'],
        'IMMUTABLE' => ['IMMUTABLE'],
        'IMPLICIT_P' => ['IMPLICIT'],
        'IMPORT_P' => ['IMPORT'],
        'INCLUDE' => ['INCLUDE'],
        'INCLUDING' => ['INCLUDING'],
        'INCREMENT' => ['INCREMENT'],
        'INDENT' => ['INDENT'],
        'INDEX' => ['INDEX'],
        'INDEXES' => ['INDEXES'],
        'INHERIT' => ['INHERIT'],
        'INHERITS' => ['INHERITS'],
        'INITIALLY' => ['INITIALLY'],
        'INLINE_P' => ['INLINE'],
        'INNER_P' => ['INNER'],
        'INOUT' => ['INOUT'],
        'INPUT_P' => ['INPUT'],
        'INSENSITIVE' => ['INSENSITIVE'],
        'INSERT' => ['INSERT'],
        'INSTEAD' => ['INSTEAD'],
        'INTEGER' => ['INTEGER'],
        'INTERSECT' => ['INTERSECT'],
        'INTERVAL' => ['INTERVAL'],
        'INTO' => ['INTO'],
        'INT_P' => ['INT'],
        'INVOKER' => ['INVOKER'],
        'IN_P' => ['IN'],
        'IS' => ['IS'],
        'ISNULL' => ['ISNULL'],
        'ISOLATION' => ['ISOLATION'],
        'JOIN' => ['JOIN'],
        'JSON' => ['JSON'],
        'JSON_ARRAY' => ['JSON_ARRAY'],
        'JSON_ARRAYAGG' => ['JSON_ARRAYAGG'],
        'JSON_EXISTS' => ['JSON_EXISTS'],
        'JSON_OBJECT' => ['JSON_OBJECT'],
        'JSON_OBJECTAGG' => ['JSON_OBJECTAGG'],
        'JSON_QUERY' => ['JSON_QUERY'],
        'JSON_SCALAR' => ['JSON_SCALAR'],
        'JSON_SERIALIZE' => ['JSON_SERIALIZE'],
        'JSON_TABLE' => ['JSON_TABLE'],
        'JSON_VALUE' => ['JSON_VALUE'],
        'KEEP' => ['KEEP'],
        'KEY' => ['KEY'],
        'KEYS' => ['KEYS'],
        'LABEL' => ['LABEL'],
        'LANGUAGE' => ['LANGUAGE'],
        'LARGE_P' => ['LARGE'],
        'LAST_P' => ['LAST'],
        'LATERAL_P' => ['LATERAL'],
        'LEADING' => ['LEADING'],
        'LEAKPROOF' => ['LEAKPROOF'],
        'LEAST' => ['LEAST'],
        'LEFT' => ['LEFT'],
        'LEVEL' => ['LEVEL'],
        'LIKE' => ['LIKE'],
        'LIMIT' => ['LIMIT'],
        'LISTEN' => ['LISTEN'],
        'LOAD' => ['LOAD'],
        'LOCAL' => ['LOCAL'],
        'LOCALTIME' => ['LOCALTIME'],
        'LOCALTIMESTAMP' => ['LOCALTIMESTAMP'],
        'LOCATION' => ['LOCATION'],
        'LOCKED' => ['LOCKED'],
        'LOCK_P' => ['LOCK'],
        'LOGGED' => ['LOGGED'],
        'MAPPING' => ['MAPPING'],
        'MATCH' => ['MATCH'],
        'MATCHED' => ['MATCHED'],
        'MATERIALIZED' => ['MATERIALIZED'],
        'MAXVALUE' => ['MAXVALUE'],
        'MERGE' => ['MERGE'],
        'MERGE_ACTION' => ['MERGE_ACTION'],
        'METHOD' => ['METHOD'],
        'MINUTE_P' => ['MINUTE'],
        'MINVALUE' => ['MINVALUE'],
        'MODE' => ['MODE'],
        'MONTH_P' => ['MONTH'],
        'MOVE' => ['MOVE'],
        'NAMES' => ['NAMES'],
        'NAME_P' => ['NAME'],
        'NATIONAL' => ['NATIONAL'],
        'NATURAL' => ['NATURAL'],
        'NCHAR' => ['NCHAR'],
        'NESTED' => ['NESTED'],
        'NEW' => ['NEW'],
        'NEXT' => ['NEXT'],
        'NFC' => ['NFC'],
        'NFD' => ['NFD'],
        'NFKC' => ['NFKC'],
        'NFKD' => ['NFKD'],
        'NO' => ['NO'],
        'NONE' => ['NONE'],
        'NORMALIZE' => ['NORMALIZE'],
        'NORMALIZED' => ['NORMALIZED'],
        'NOT' => ['NOT'],
        'NOTHING' => ['NOTHING'],
        'NOTIFY' => ['NOTIFY'],
        'NOTNULL' => ['NOTNULL'],
        'NOWAIT' => ['NOWAIT'],
        'NULLIF' => ['NULLIF'],
        'NULLS_P' => ['NULLS'],
        'NULL_P' => ['NULL'],
        'NUMERIC' => ['NUMERIC'],
        'OBJECT_P' => ['OBJECT'],
        'OF' => ['OF'],
        'OFF' => ['OFF'],
        'OFFSET' => ['OFFSET'],
        'OIDS' => ['OIDS'],
        'OLD' => ['OLD'],
        'OMIT' => ['OMIT'],
        'ON' => ['ON'],
        'ONLY' => ['ONLY'],
        'OPERATOR' => ['OPERATOR'],
        'OPTION' => ['OPTION'],
        'OPTIONS' => ['OPTIONS'],
        'OR' => ['OR'],
        'ORDER' => ['ORDER'],
        'ORDINALITY' => ['ORDINALITY'],
        'OTHERS' => ['OTHERS'],
        'OUTER_P' => ['OUTER'],
        'OUT_P' => ['OUT'],
        'OVER' => ['OVER'],
        'OVERLAPS' => ['OVERLAPS'],
        'OVERLAY' => ['OVERLAY'],
        'OVERRIDING' => ['OVERRIDING'],
        'OWNED' => ['OWNED'],
        'OWNER' => ['OWNER'],
        'PARALLEL' => ['PARALLEL'],
        'PARAMETER' => ['PARAMETER'],
        'PARSER' => ['PARSER'],
        'PARTIAL' => ['PARTIAL'],
        'PARTITION' => ['PARTITION'],
        'PASSING' => ['PASSING'],
        'PASSWORD' => ['PASSWORD'],
        'PATH' => ['PATH'],
        'PLACING' => ['PLACING'],
        'PLAN' => ['PLAN'],
        'PLANS' => ['PLANS'],
        'POLICY' => ['POLICY'],
        'POSITION' => ['POSITION'],
        'PRECEDING' => ['PRECEDING'],
        'PRECISION' => ['PRECISION'],
        'PREPARE' => ['PREPARE'],
        'PREPARED' => ['PREPARED'],
        'PRESERVE' => ['PRESERVE'],
        'PRIMARY' => ['PRIMARY'],
        'PRIOR' => ['PRIOR'],
        'PRIVILEGES' => ['PRIVILEGES'],
        'PROCEDURAL' => ['PROCEDURAL'],
        'PROCEDURE' => ['PROCEDURE'],
        'PROCEDURES' => ['PROCEDURES'],
        'PROGRAM' => ['PROGRAM'],
        'PUBLICATION' => ['PUBLICATION'],
        'QUOTE' => ['QUOTE'],
        'QUOTES' => ['QUOTES'],
        'RANGE' => ['RANGE'],
        'READ' => ['READ'],
        'REAL' => ['REAL'],
        'REASSIGN' => ['REASSIGN'],
        'RECHECK' => ['RECHECK'],
        'RECURSIVE' => ['RECURSIVE'],
        'REFERENCES' => ['REFERENCES'],
        'REFERENCING' => ['REFERENCING'],
        'REFRESH' => ['REFRESH'],
        'REF_P' => ['REF'],
        'REINDEX' => ['REINDEX'],
        'RELATIVE_P' => ['RELATIVE'],
        'RELEASE' => ['RELEASE'],
        'RENAME' => ['RENAME'],
        'REPEATABLE' => ['REPEATABLE'],
        'REPLACE' => ['REPLACE'],
        'REPLICA' => ['REPLICA'],
        'RESET' => ['RESET'],
        'RESTART' => ['RESTART'],
        'RESTRICT' => ['RESTRICT'],
        'RETURN' => ['RETURN'],
        'RETURNING' => ['RETURNING'],
        'RETURNS' => ['RETURNS'],
        'REVOKE' => ['REVOKE'],
        'RIGHT' => ['RIGHT'],
        'ROLE' => ['ROLE'],
        'ROLLBACK' => ['ROLLBACK'],
        'ROLLUP' => ['ROLLUP'],
        'ROUTINE' => ['ROUTINE'],
        'ROUTINES' => ['ROUTINES'],
        'ROW' => ['ROW'],
        'ROWS' => ['ROWS'],
        'RULE' => ['RULE'],
        'SAVEPOINT' => ['SAVEPOINT'],
        'SCALAR' => ['SCALAR'],
        'SCHEMA' => ['SCHEMA'],
        'SCHEMAS' => ['SCHEMAS'],
        'SCROLL' => ['SCROLL'],
        'SEARCH' => ['SEARCH'],
        'SECOND_P' => ['SECOND'],
        'SECURITY' => ['SECURITY'],
        'SELECT' => ['SELECT'],
        'SEQUENCE' => ['SEQUENCE'],
        'SEQUENCES' => ['SEQUENCES'],
        'SERIALIZABLE' => ['SERIALIZABLE'],
        'SERVER' => ['SERVER'],
        'SESSION' => ['SESSION'],
        'SESSION_USER' => ['SESSION_USER'],
        'SET' => ['SET'],
        'SETOF' => ['SETOF'],
        'SETS' => ['SETS'],
        'SHARE' => ['SHARE'],
        'SHOW' => ['SHOW'],
        'SIMILAR' => ['SIMILAR'],
        'SIMPLE' => ['SIMPLE'],
        'SKIP' => ['SKIP'],
        'SMALLINT' => ['SMALLINT'],
        'SNAPSHOT' => ['SNAPSHOT'],
        'SOME' => ['SOME'],
        'SOURCE' => ['SOURCE'],
        'SQL_P' => ['SQL'],
        'STABLE' => ['STABLE'],
        'STANDALONE_P' => ['STANDALONE'],
        'START' => ['START'],
        'STATEMENT' => ['STATEMENT'],
        'STATISTICS' => ['STATISTICS'],
        'STDIN' => ['STDIN'],
        'STDOUT' => ['STDOUT'],
        'STORAGE' => ['STORAGE'],
        'STORED' => ['STORED'],
        'STRICT_P' => ['STRICT'],
        'STRING_P' => ['STRING'],
        'STRIP_P' => ['STRIP'],
        'SUBSCRIPTION' => ['SUBSCRIPTION'],
        'SUBSTRING' => ['SUBSTRING'],
        'SUPPORT' => ['SUPPORT'],
        'SYMMETRIC' => ['SYMMETRIC'],
        'SYSID' => ['SYSID'],
        'SYSTEM_P' => ['SYSTEM'],
        'SYSTEM_USER' => ['SYSTEM_USER'],
        'TABLE' => ['TABLE'],
        'TABLES' => ['TABLES'],
        'TABLESAMPLE' => ['TABLESAMPLE'],
        'TABLESPACE' => ['TABLESPACE'],
        'TARGET' => ['TARGET'],
        'TEMP' => ['TEMP'],
        'TEMPLATE' => ['TEMPLATE'],
        'TEMPORARY' => ['TEMPORARY'],
        'TEXT_P' => ['TEXT'],
        'THEN' => ['THEN'],
        'TIES' => ['TIES'],
        'TIME' => ['TIME'],
        'TIMESTAMP' => ['TIMESTAMP'],
        'TO' => ['TO'],
        'TRAILING' => ['TRAILING'],
        'TRANSACTION' => ['TRANSACTION'],
        'TRANSFORM' => ['TRANSFORM'],
        'TREAT' => ['TREAT'],
        'TRIGGER' => ['TRIGGER'],
        'TRIM' => ['TRIM'],
        'TRUE_P' => ['TRUE'],
        'TRUNCATE' => ['TRUNCATE'],
        'TRUSTED' => ['TRUSTED'],
        'TYPES_P' => ['TYPES'],
        'TYPE_P' => ['TYPE'],
        'UESCAPE' => ['UESCAPE'],
        'UNBOUNDED' => ['UNBOUNDED'],
        'UNCOMMITTED' => ['UNCOMMITTED'],
        'UNCONDITIONAL' => ['UNCONDITIONAL'],
        'UNENCRYPTED' => ['UNENCRYPTED'],
        'UNION' => ['UNION'],
        'UNIQUE' => ['UNIQUE'],
        'UNKNOWN' => ['UNKNOWN'],
        'UNLISTEN' => ['UNLISTEN'],
        'UNLOGGED' => ['UNLOGGED'],
        'UNTIL' => ['UNTIL'],
        'UPDATE' => ['UPDATE'],
        'USER' => ['USER'],
        'USING' => ['USING'],
        'VACUUM' => ['VACUUM'],
        'VALID' => ['VALID'],
        'VALIDATE' => ['VALIDATE'],
        'VALIDATOR' => ['VALIDATOR'],
        'VALUES' => ['VALUES'],
        'VALUE_P' => ['VALUE'],
        'VARCHAR' => ['VARCHAR'],
        'VARIADIC' => ['VARIADIC'],
        'VARYING' => ['VARYING'],
        'VERBOSE' => ['VERBOSE'],
        'VERSION_P' => ['VERSION'],
        'VIEW' => ['VIEW'],
        'VIEWS' => ['VIEWS'],
        'VOLATILE' => ['VOLATILE'],
        'WHEN' => ['WHEN'],
        'WHERE' => ['WHERE'],
        'WHITESPACE_P' => ['WHITESPACE'],
        'WINDOW' => ['WINDOW'],
        'WITH' => ['WITH'],
        'WITHIN' => ['WITHIN'],
        'WITHOUT' => ['WITHOUT'],
        'WORK' => ['WORK'],
        'WRAPPER' => ['WRAPPER'],
        'WRITE' => ['WRITE'],
        'XMLATTRIBUTES' => ['XMLATTRIBUTES'],
        'XMLCONCAT' => ['XMLCONCAT'],
        'XMLELEMENT' => ['XMLELEMENT'],
        'XMLEXISTS' => ['XMLEXISTS'],
        'XMLFOREST' => ['XMLFOREST'],
        'XMLNAMESPACES' => ['XMLNAMESPACES'],
        'XMLPARSE' => ['XMLPARSE'],
        'XMLPI' => ['XMLPI'],
        'XMLROOT' => ['XMLROOT'],
        'XMLSERIALIZE' => ['XMLSERIALIZE'],
        'XMLTABLE' => ['XMLTABLE'],
        'XML_P' => ['XML'],
        'YEAR_P' => ['YEAR'],
        'YES_P' => ['YES'],
        'ZONE' => ['ZONE'],
    ];

}
