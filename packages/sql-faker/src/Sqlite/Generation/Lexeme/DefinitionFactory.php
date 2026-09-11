<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Lexeme;

use RuntimeException;
use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexicalDefinition;
use SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator;
use SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\ChoiceDomain;
use SqlFaker\Grammar\Generation\Value\IdentifierDomain;
use SqlFaker\Grammar\Generation\Value\IntegerDomain;
use SqlFaker\Grammar\Generation\Value\QuotedDomain;
use SqlFaker\Grammar\Generation\Value\RepeatDomain;
use SqlFaker\Grammar\Generation\Value\SequenceDomain;
use SqlFaker\Grammar\Generation\Value\WordDomain;
use SqlFaker\Grammar\Generation\Version\VersionCase;
use SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator;
use SqlFaker\Grammar\SqlVersion;

/**
 * Composes the reviewed SQLite tokenizer cases without post-serialization whitespace changes.
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/build.c
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/global.c
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/tokenize.c
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/tool/mkkeywordhash.c
 */
final class DefinitionFactory
{
    /**
     * Composes the selected release directly from the declarations in this file.
     * @throws RuntimeException When the exact release is unsupported
     */
    public function create(string $version): LexicalDefinition
    {
        SqlVersion::resolve('sqlite', $version);
        $keywords = [
            'ABORT' => ['ABORT'],
            'ACTION' => ['ACTION'],
            'ADD' => ['ADD'],
            'AFTER' => ['AFTER'],
            'ALL' => ['ALL'],
            'ALTER' => ['ALTER'],
            'ALWAYS' => ['ALWAYS'],
            'ANALYZE' => ['ANALYZE'],
            'AND' => ['AND'],
            'AS' => ['AS'],
            'ASC' => ['ASC'],
            'ATTACH' => ['ATTACH'],
            'AUTOINCR' => ['AUTOINCREMENT'],
            'BEFORE' => ['BEFORE'],
            'BEGIN' => ['BEGIN'],
            'BETWEEN' => ['BETWEEN'],
            'BY' => ['BY'],
            'CASCADE' => ['CASCADE'],
            'CASE' => ['CASE'],
            'CAST' => ['CAST'],
            'CHECK' => ['CHECK'],
            'COLLATE' => ['COLLATE'],
            'COLUMNKW' => ['COLUMN'],
            'COMMIT' => ['COMMIT'],
            'CONFLICT' => ['CONFLICT'],
            'CONSTRAINT' => ['CONSTRAINT'],
            'CREATE' => ['CREATE'],
            'CTIME_KW' => ['CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP'],
            'CURRENT' => ['CURRENT'],
            'DATABASE' => ['DATABASE'],
            'DEFAULT' => ['DEFAULT'],
            'DEFERRABLE' => ['DEFERRABLE'],
            'DEFERRED' => ['DEFERRED'],
            'DELETE' => ['DELETE'],
            'DESC' => ['DESC'],
            'DETACH' => ['DETACH'],
            'DISTINCT' => ['DISTINCT'],
            'DO' => ['DO'],
            'DROP' => ['DROP'],
            'EACH' => ['EACH'],
            'ELSE' => ['ELSE'],
            'END' => ['END'],
            'ESCAPE' => ['ESCAPE'],
            'EXCEPT' => ['EXCEPT'],
            'EXCLUDE' => ['EXCLUDE'],
            'EXCLUSIVE' => ['EXCLUSIVE'],
            'EXISTS' => ['EXISTS'],
            'EXPLAIN' => ['EXPLAIN'],
            'FAIL' => ['FAIL'],
            'FILTER' => ['FILTER'],
            'FIRST' => ['FIRST'],
            'FOLLOWING' => ['FOLLOWING'],
            'FOR' => ['FOR'],
            'FOREIGN' => ['FOREIGN'],
            'FROM' => ['FROM'],
            'GENERATED' => ['GENERATED'],
            'GROUP' => ['GROUP'],
            'GROUPS' => ['GROUPS'],
            'HAVING' => ['HAVING'],
            'IF' => ['IF'],
            'IGNORE' => ['IGNORE'],
            'IMMEDIATE' => ['IMMEDIATE'],
            'IN' => ['IN'],
            'INDEX' => ['INDEX'],
            'INDEXED' => ['INDEXED'],
            'INITIALLY' => ['INITIALLY'],
            'INSERT' => ['INSERT'],
            'INSTEAD' => ['INSTEAD'],
            'INTERSECT' => ['INTERSECT'],
            'INTO' => ['INTO'],
            'IS' => ['IS'],
            'ISNULL' => ['ISNULL'],
            'JOIN' => ['JOIN'],
            'JOIN_KW' => ['CROSS', 'FULL', 'INNER', 'LEFT', 'NATURAL', 'OUTER', 'RIGHT'],
            'KEY' => ['KEY'],
            'LAST' => ['LAST'],
            'LIKE_KW' => ['GLOB', 'LIKE', 'REGEXP'],
            'LIMIT' => ['LIMIT'],
            'MATCH' => ['MATCH'],
            'MATERIALIZED' => ['MATERIALIZED'],
            'NO' => ['NO'],
            'NOT' => ['NOT'],
            'NOTHING' => ['NOTHING'],
            'NOTNULL' => ['NOTNULL'],
            'NULL' => ['NULL'],
            'NULLS' => ['NULLS'],
            'OF' => ['OF'],
            'OFFSET' => ['OFFSET'],
            'ON' => ['ON'],
            'OR' => ['OR'],
            'ORDER' => ['ORDER'],
            'OTHERS' => ['OTHERS'],
            'OVER' => ['OVER'],
            'PARTITION' => ['PARTITION'],
            'PLAN' => ['PLAN'],
            'PRAGMA' => ['PRAGMA'],
            'PRECEDING' => ['PRECEDING'],
            'PRIMARY' => ['PRIMARY'],
            'QUERY' => ['QUERY'],
            'RAISE' => ['RAISE'],
            'RANGE' => ['RANGE'],
            'RECURSIVE' => ['RECURSIVE'],
            'REFERENCES' => ['REFERENCES'],
            'REINDEX' => ['REINDEX'],
            'RELEASE' => ['RELEASE'],
            'RENAME' => ['RENAME'],
            'REPLACE' => ['REPLACE'],
            'RESTRICT' => ['RESTRICT'],
            'RETURNING' => ['RETURNING'],
            'ROLLBACK' => ['ROLLBACK'],
            'ROW' => ['ROW'],
            'ROWS' => ['ROWS'],
            'SAVEPOINT' => ['SAVEPOINT'],
            'SELECT' => ['SELECT'],
            'SET' => ['SET'],
            'TABLE' => ['TABLE'],
            'TEMP' => ['TEMP', 'TEMPORARY'],
            'THEN' => ['THEN'],
            'TIES' => ['TIES'],
            'TO' => ['TO'],
            'TRANSACTION' => ['TRANSACTION'],
            'TRIGGER' => ['TRIGGER'],
            'UNBOUNDED' => ['UNBOUNDED'],
            'UNION' => ['UNION'],
            'UNIQUE' => ['UNIQUE'],
            'UPDATE' => ['UPDATE'],
            'USING' => ['USING'],
            'VACUUM' => ['VACUUM'],
            'VALUES' => ['VALUES'],
            'VIEW' => ['VIEW'],
            'VIRTUAL' => ['VIRTUAL'],
            'WHEN' => ['WHEN'],
            'WHERE' => ['WHERE'],
            'WINDOW' => ['WINDOW'],
            'WITH' => ['WITH'],
            'WITHIN' => ['WITHIN'],
            'WITHOUT' => ['WITHOUT'],
        ];
        return new LexicalDefinition(
            version: $version,
            dialect: 'SQLite',
            lexemes: new ChoiceLexemeGenerator(
                new VersionedLexemeGenerator(
                    $version,
                    new VersionCase(
                        ['sqlite-3.47.2'],
                        new MatchingLexemeGenerator(
                            static fn (LexemeInput $input): bool => isset($keywords[$input->terminal()->name]) && !($input->terminal()->name === 'JOIN_KW' && $input->terminal()->within('joinop')),
                            new WindowNameLexemeGenerator(
                                new RegisteredLexemeGenerator(
                                    $keywords,
                                    'tool/mkkeywordhash.c',
                                    [],
                                ),
                            ),
                        ),
                        'sqlite-3.47.2-keywords',
                    ),
                ),
                new VersionedLexemeGenerator(
                    $version,
                    new VersionCase(
                        ['sqlite-3.47.2'],
                        new ChoiceLexemeGenerator(
                            new ChoiceLexemeGenerator(
                                new ChoiceLexemeGenerator(
                                    new ValueLexemeGenerator(
                                        'ID',
                                        new ChoiceDomain(
                                            new QuotedDomain(
                                                '"',
                                                minimum: 1,
                                                maximum: 64,
                                            ),
                                            new IdentifierDomain(
                                                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_',
                                                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$',
                                            ),
                                            new QuotedDomain(
                                                '`',
                                                minimum: 1,
                                                maximum: 64,
                                            ),
                                            new QuotedDomain(
                                                '[',
                                                minimum: 1,
                                                maximum: 64,
                                                closing: ']',
                                            ),
                                        ),
                                        ['name'],
                                        'identifier',
                                        'src/tokenize.c:CC_ID/CC_QUOTE',
                                    ),
                                    new ValueLexemeGenerator(
                                        'id',
                                        new ChoiceDomain(
                                            new QuotedDomain(
                                                '"',
                                                minimum: 1,
                                                maximum: 64,
                                            ),
                                            new IdentifierDomain(
                                                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_',
                                                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$',
                                            ),
                                            new QuotedDomain(
                                                '`',
                                                minimum: 1,
                                                maximum: 64,
                                            ),
                                            new QuotedDomain(
                                                '[',
                                                minimum: 1,
                                                maximum: 64,
                                                closing: ']',
                                            ),
                                        ),
                                        ['name'],
                                        'identifier',
                                        'src/tokenize.c:CC_ID/CC_QUOTE',
                                    ),
                                    new ValueLexemeGenerator(
                                        'idj',
                                        new ChoiceDomain(
                                            new QuotedDomain(
                                                '"',
                                                minimum: 1,
                                                maximum: 64,
                                            ),
                                            new IdentifierDomain(
                                                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_',
                                                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$',
                                            ),
                                            new QuotedDomain(
                                                '`',
                                                minimum: 1,
                                                maximum: 64,
                                            ),
                                            new QuotedDomain(
                                                '[',
                                                minimum: 1,
                                                maximum: 64,
                                                closing: ']',
                                            ),
                                        ),
                                        ['name'],
                                        'identifier',
                                        'src/tokenize.c:CC_ID/CC_QUOTE',
                                    ),
                                    new ValueLexemeGenerator(
                                        'ANY',
                                        new ChoiceDomain(
                                            new QuotedDomain(
                                                '"',
                                                minimum: 1,
                                                maximum: 64,
                                            ),
                                            new IdentifierDomain(
                                                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_',
                                                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$',
                                            ),
                                            new QuotedDomain(
                                                '`',
                                                minimum: 1,
                                                maximum: 64,
                                            ),
                                            new QuotedDomain(
                                                '[',
                                                minimum: 1,
                                                maximum: 64,
                                                closing: ']',
                                            ),
                                        ),
                                        ['name'],
                                        'identifier',
                                        'src/tokenize.c:CC_ID/CC_QUOTE',
                                    ),
                                    new ValueLexemeGenerator(
                                        'VARIABLE',
                                        new ChoiceDomain(
                                            new WordDomain(['?']),
                                            new SequenceDomain(
                                                new WordDomain(['?']),
                                                new IntegerDomain(
                                                    '1',
                                                    '32766',
                                                    0,
                                                ),
                                            ),
                                            new SequenceDomain(
                                                new WordDomain([':', '@', '$']),
                                                new IdentifierDomain(
                                                    'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_',
                                                    'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$',
                                                    'v',
                                                    64,
                                                ),
                                            ),
                                        ),
                                        ['?1', ':value', '@value', '$value'],
                                        'parameter',
                                        'src/tokenize.c:CC_VARNUM/CC_VARALPHA',
                                    ),
                                ),
                                new ChoiceLexemeGenerator(
                                    new ValueLexemeGenerator(
                                        'INTEGER',
                                        new ChoiceDomain(
                                            new CharacterDomain(
                                                ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                1,
                                                19,
                                            ),
                                            new SequenceDomain(
                                                new WordDomain(['0x', '0X']),
                                                new CharacterDomain(
                                                    [
                                                        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f',
                                                        'A', 'B', 'C', 'D', 'E', 'F',
                                                    ],
                                                    1,
                                                    16,
                                                ),
                                            ),
                                        ),
                                        ['1', '0', '2'],
                                        'number',
                                        'src/tokenize.c:CC_DIGIT',
                                    ),
                                    new ValueLexemeGenerator(
                                        'number',
                                        new CharacterDomain(
                                            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                            1,
                                            19,
                                        ),
                                        ['1'],
                                        'number',
                                        'src/parse.y:number',
                                    ),
                                    new ValueLexemeGenerator(
                                        'FLOAT',
                                        new ChoiceDomain(
                                            new ChoiceDomain(
                                                new SequenceDomain(
                                                    new CharacterDomain(
                                                        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                        1,
                                                        19,
                                                    ),
                                                    new WordDomain(['.']),
                                                    new CharacterDomain(
                                                        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                        0,
                                                        30,
                                                    ),
                                                ),
                                                new SequenceDomain(
                                                    new WordDomain(['.']),
                                                    new CharacterDomain(
                                                        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                        1,
                                                        19,
                                                    ),
                                                ),
                                            ),
                                            new SequenceDomain(
                                                new ChoiceDomain(
                                                    new CharacterDomain(
                                                        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                        1,
                                                        19,
                                                    ),
                                                    new ChoiceDomain(
                                                        new SequenceDomain(
                                                            new CharacterDomain(
                                                                ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                                1,
                                                                19,
                                                            ),
                                                            new WordDomain(['.']),
                                                            new CharacterDomain(
                                                                ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                                0,
                                                                30,
                                                            ),
                                                        ),
                                                        new SequenceDomain(
                                                            new WordDomain(['.']),
                                                            new CharacterDomain(
                                                                ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                                1,
                                                                19,
                                                            ),
                                                        ),
                                                    ),
                                                ),
                                                new SequenceDomain(
                                                    new WordDomain(['e', 'E']),
                                                    new WordDomain(['', '+', '-']),
                                                    new CharacterDomain(
                                                        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                        1,
                                                        3,
                                                    ),
                                                ),
                                            ),
                                        ),
                                        ['1.5', '.5', '1e2'],
                                        'number',
                                        'src/tokenize.c:CC_DIGIT:float',
                                    ),
                                    new ValueLexemeGenerator(
                                        'QNUMBER',
                                        new SequenceDomain(
                                            new CharacterDomain(
                                                ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                1,
                                                19,
                                            ),
                                            new RepeatDomain(
                                                new SequenceDomain(
                                                    new WordDomain(['_']),
                                                    new CharacterDomain(
                                                        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
                                                        1,
                                                        19,
                                                    ),
                                                ),
                                                1,
                                                8,
                                            ),
                                        ),
                                        ['1_0'],
                                        'number',
                                        'src/tokenize.c:TK_QNUMBER',
                                    ),
                                ),
                                new ChoiceLexemeGenerator(
                                    new ValueLexemeGenerator(
                                        'STRING',
                                        new QuotedDomain(
                                            "'",
                                            alphabet: [
                                                "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08", "\x09", "\x0a", "\x0b", "\x0c", "\x0d", "\x0e", "\x0f", "\x10",
                                                "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17", "\x18", "\x19", "\x1a", "\x1b", "\x1c", "\x1d", "\x1e", "\x1f", ' ',
                                                '!', '"', '#', '$', '%', '&', '\'', '(', ')', '*', '+', ',', '-', '.', '/', '0',
                                                '1', '2', '3', '4', '5', '6', '7', '8', '9', ':', ';', '<', '=', '>', '?', '@',
                                                'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
                                                'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '[', '\\', ']', '^', '_', '`',
                                                'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p',
                                                'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '{', '|', '}', '~', "\x7f", 'é',
                                                '猫', '😀',
                                            ],
                                        ),
                                        ["'text'", "'a''b'"],
                                        'string',
                                        'src/tokenize.c:CC_QUOTE',
                                    ),
                                    new ValueLexemeGenerator(
                                        'ids',
                                        new QuotedDomain(
                                            "'",
                                            alphabet: [
                                                "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08", "\x09", "\x0a", "\x0b", "\x0c", "\x0d", "\x0e", "\x0f", "\x10",
                                                "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17", "\x18", "\x19", "\x1a", "\x1b", "\x1c", "\x1d", "\x1e", "\x1f", ' ',
                                                '!', '"', '#', '$', '%', '&', '\'', '(', ')', '*', '+', ',', '-', '.', '/', '0',
                                                '1', '2', '3', '4', '5', '6', '7', '8', '9', ':', ';', '<', '=', '>', '?', '@',
                                                'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
                                                'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '[', '\\', ']', '^', '_', '`',
                                                'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p',
                                                'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '{', '|', '}', '~', "\x7f", 'é',
                                                '猫', '😀',
                                            ],
                                        ),
                                        ["'text'"],
                                        'string',
                                        'src/parse.y:ids',
                                    ),
                                    new ValueLexemeGenerator(
                                        'BLOB',
                                        new SequenceDomain(
                                            new WordDomain(['X', 'x']),
                                            new CharacterDomain(
                                                [
                                                    '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f',
                                                    'A', 'B', 'C', 'D', 'E', 'F',
                                                ],
                                                0,
                                                127,
                                                "'",
                                                "'",
                                                2,
                                            ),
                                        ),
                                        ["X'00'", "X''"],
                                        'string',
                                        'src/tokenize.c:CC_X',
                                    ),
                                ),
                            ),
                            new ChoiceLexemeGenerator(
                                new MatchingLexemeGenerator(
                                    'LP',
                                    new FixedLexemeGenerator('(', 'symbol', 'src/tokenize.c:LP'),
                                ),
                                new MatchingLexemeGenerator(
                                    'RP',
                                    new FixedLexemeGenerator(')', 'symbol', 'src/tokenize.c:RP'),
                                ),
                                new MatchingLexemeGenerator(
                                    'SEMI',
                                    new FixedLexemeGenerator(';', 'symbol', 'src/tokenize.c:SEMI'),
                                ),
                                new MatchingLexemeGenerator(
                                    'COMMA',
                                    new FixedLexemeGenerator(',', 'symbol', 'src/tokenize.c:COMMA'),
                                ),
                                new MatchingLexemeGenerator(
                                    'DOT',
                                    new FixedLexemeGenerator('.', 'symbol', 'src/tokenize.c:DOT'),
                                ),
                                new MatchingLexemeGenerator(
                                    'EQ',
                                    new FixedLexemeGenerator('=', 'symbol', 'src/tokenize.c:EQ'),
                                ),
                                new MatchingLexemeGenerator(
                                    'LT',
                                    new FixedLexemeGenerator('<', 'symbol', 'src/tokenize.c:LT'),
                                ),
                                new MatchingLexemeGenerator(
                                    'LE',
                                    new FixedLexemeGenerator('<=', 'symbol', 'src/tokenize.c:LE'),
                                ),
                                new MatchingLexemeGenerator(
                                    'GT',
                                    new FixedLexemeGenerator('>', 'symbol', 'src/tokenize.c:GT'),
                                ),
                                new MatchingLexemeGenerator(
                                    'GE',
                                    new FixedLexemeGenerator('>=', 'symbol', 'src/tokenize.c:GE'),
                                ),
                                new MatchingLexemeGenerator(
                                    'NE',
                                    new FixedLexemeGenerator('<>', 'symbol', 'src/tokenize.c:NE'),
                                ),
                                new MatchingLexemeGenerator(
                                    'PLUS',
                                    new FixedLexemeGenerator('+', 'symbol', 'src/tokenize.c:PLUS'),
                                ),
                                new MatchingLexemeGenerator(
                                    'MINUS',
                                    new FixedLexemeGenerator('-', 'symbol', 'src/tokenize.c:MINUS'),
                                ),
                                new MatchingLexemeGenerator(
                                    'STAR',
                                    new FixedLexemeGenerator('*', 'symbol', 'src/tokenize.c:STAR'),
                                ),
                                new MatchingLexemeGenerator(
                                    'SLASH',
                                    new FixedLexemeGenerator('/', 'symbol', 'src/tokenize.c:SLASH'),
                                ),
                                new MatchingLexemeGenerator(
                                    'REM',
                                    new FixedLexemeGenerator('%', 'symbol', 'src/tokenize.c:REM'),
                                ),
                                new MatchingLexemeGenerator(
                                    'BITAND',
                                    new FixedLexemeGenerator('&', 'symbol', 'src/tokenize.c:BITAND'),
                                ),
                                new MatchingLexemeGenerator(
                                    'BITOR',
                                    new FixedLexemeGenerator('|', 'symbol', 'src/tokenize.c:BITOR'),
                                ),
                                new MatchingLexemeGenerator(
                                    'BITNOT',
                                    new FixedLexemeGenerator('~', 'symbol', 'src/tokenize.c:BITNOT'),
                                ),
                                new MatchingLexemeGenerator(
                                    'LSHIFT',
                                    new FixedLexemeGenerator('<<', 'symbol', 'src/tokenize.c:LSHIFT'),
                                ),
                                new MatchingLexemeGenerator(
                                    'RSHIFT',
                                    new FixedLexemeGenerator('>>', 'symbol', 'src/tokenize.c:RSHIFT'),
                                ),
                                new MatchingLexemeGenerator(
                                    'CONCAT',
                                    new FixedLexemeGenerator('||', 'symbol', 'src/tokenize.c:CONCAT'),
                                ),
                                new MatchingLexemeGenerator(
                                    'PTR',
                                    new FixedLexemeGenerator('->', 'symbol', 'src/tokenize.c:PTR'),
                                ),
                                new MatchingLexemeGenerator(
                                    'STRICT_TABLE_OPTION',
                                    new FixedLexemeGenerator('STRICT', 'symbol', 'src/tokenize.c:STRICT_TABLE_OPTION'),
                                ),
                                new MatchingLexemeGenerator(
                                    'ROWID_TABLE_OPTION',
                                    new FixedLexemeGenerator('ROWID', 'symbol', 'src/tokenize.c:ROWID_TABLE_OPTION'),
                                ),
                            ),
                            new MatchingLexemeGenerator(
                                'STRICT_COLUMN_TYPE',
                                new ChoiceLexemeGenerator(
                                    new FixedLexemeGenerator('ANY', 'type-name', 'src/global.c:sqlite3StdType'),
                                    new FixedLexemeGenerator('BLOB', 'type-name', 'src/global.c:sqlite3StdType'),
                                    new FixedLexemeGenerator('INT', 'type-name', 'src/global.c:sqlite3StdType'),
                                    new FixedLexemeGenerator('INTEGER', 'type-name', 'src/global.c:sqlite3StdType'),
                                    new FixedLexemeGenerator('REAL', 'type-name', 'src/global.c:sqlite3StdType'),
                                    new FixedLexemeGenerator('TEXT', 'type-name', 'src/global.c:sqlite3StdType'),
                                ),
                            ),
                            new ValueLexemeGenerator(
                                'GENERATED_STORAGE',
                                new WordDomain(['VIRTUAL', 'STORED'], true),
                                ['VIRTUAL', 'STORED'],
                                'identifier',
                                'src/build.c:sqlite3AddGenerated',
                            ),
                            new JoinLexemeGenerator(
                                $keywords['JOIN_KW'],
                            ),
                        ),
                        'sqlite-3.47.2-scanner',
                    ),
                ),
            ),
            spacing: new CombinedSpacingRule(),
            keywords: $keywords,
        );
    }
}
