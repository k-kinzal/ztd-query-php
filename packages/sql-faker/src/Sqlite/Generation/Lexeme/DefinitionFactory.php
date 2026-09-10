<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Lexeme;

use RuntimeException;
use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\CandidateResolver;
use SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator;
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
 */
final class DefinitionFactory
{
    /**
     * Composes the selected release directly from the declarations in this file.
     * @throws RuntimeException When the exact release is unsupported
     */
    public function create(string $version): ReverseLexemeGenerator
    {
        return new ReverseLexemeGenerator($this->lexemes($version), new CandidateResolver(new CombinedSpacingRule()), $version, 'SQLite');
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
            new VersionedLexemeGenerator($version, new VersionCase(['sqlite-3.47.2'], new ChoiceLexemeGenerator(
                $this->values(),
                $this->symbols(),
                $this->strictTypes(),
                new ValueLexemeGenerator('GENERATED_STORAGE', new WordDomain(['VIRTUAL', 'STORED'], true), ['VIRTUAL', 'STORED'], 'identifier', 'src/build.c:sqlite3AddGenerated'),
                new JoinLexemeGenerator($keywords['JOIN_KW'] ?? []),
            ), 'sqlite-3.47.2-scanner')),
        );
    }

    /**
     * Declares multi-character scanner operators as single lexemes.
     */
    public function symbols(): LexemeGenerator
    {
        $fixed = ['LP' => '(', 'RP' => ')', 'SEMI' => ';', 'COMMA' => ',', 'DOT' => '.',
            'EQ' => '=', 'LT' => '<', 'LE' => '<=', 'GT' => '>', 'GE' => '>=', 'NE' => '<>',
            'PLUS' => '+', 'MINUS' => '-', 'STAR' => '*', 'SLASH' => '/', 'REM' => '%',
            'BITAND' => '&', 'BITOR' => '|', 'BITNOT' => '~', 'LSHIFT' => '<<', 'RSHIFT' => '>>',
            'CONCAT' => '||', 'PTR' => '->', 'STRICT_TABLE_OPTION' => 'STRICT', 'ROWID_TABLE_OPTION' => 'ROWID'];
        $generators = [];
        foreach ($fixed as $terminal => $text) {
            $generators[] = new MatchingLexemeGenerator($terminal, new FixedLexemeGenerator($text, 'symbol', 'src/tokenize.c:' . $terminal));
        }
        return new ChoiceLexemeGenerator(...$generators);
    }
    /**
     * Declares the complete STRICT type domain from global.c/sqlite3StdType.
     */
    public function strictTypes(): LexemeGenerator
    {
        $types = [];
        foreach (['ANY', 'BLOB', 'INT', 'INTEGER', 'REAL', 'TEXT'] as $name) {
            $types[] = new FixedLexemeGenerator($name, 'type-name', 'src/global.c:sqlite3StdType');
        }
        return new MatchingLexemeGenerator('STRICT_COLUMN_TYPE', new ChoiceLexemeGenerator(...$types));
    }

    /**
     * sqlite3GetToken value cases.
     */
    public function values(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator($this->names(), $this->numbers(), $this->strings());
    }

    /**
     * CC_ID / CC_QUOTE / CC_VARNUM / CC_VARALPHA.
     * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/tokenize.c
     */
    public function names(): LexemeGenerator
    {
        $name = new ChoiceDomain(
            new QuotedDomain('"', minimum: 1, maximum: 64),
            new IdentifierDomain('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$'),
            new QuotedDomain('`', minimum: 1, maximum: 64),
            new QuotedDomain('[', minimum: 1, maximum: 64, closing: ']'),
        );
        $names = [];
        foreach (['ID', 'id', 'idj', 'ANY'] as $terminal) {
            $names[] = new ValueLexemeGenerator($terminal, $name, ['name'], 'identifier', 'src/tokenize.c:CC_ID/CC_QUOTE');
        }
        $names[] = new ValueLexemeGenerator('VARIABLE', new ChoiceDomain(
            new WordDomain(['?']),
            new SequenceDomain(new WordDomain(['?']), new IntegerDomain('1', '32766', 0)),
            new SequenceDomain(new WordDomain([':', '@', '$']), new IdentifierDomain('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$', 'v', 64)),
        ), ['?1', ':value', '@value', '$value'], 'parameter', 'src/tokenize.c:CC_VARNUM/CC_VARALPHA');
        return new ChoiceLexemeGenerator(...$names);
    }

    /**
     * CC_DIGIT: decimal, hexadecimal, real and TK_QNUMBER digit separators.
     * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/tokenize.c
     */
    public function numbers(): LexemeGenerator
    {
        $digits = new CharacterDomain(str_split('0123456789'), 1, 19);
        $decimal = new ChoiceDomain(new SequenceDomain($digits, new WordDomain(['.']), new CharacterDomain(str_split('0123456789'), 0, 30)), new SequenceDomain(new WordDomain(['.']), $digits));
        $exponent = new SequenceDomain(new WordDomain(['e', 'E']), new WordDomain(['', '+', '-']), new CharacterDomain(str_split('0123456789'), 1, 3));
        return new ChoiceLexemeGenerator(
            new ValueLexemeGenerator('INTEGER', new ChoiceDomain($digits, new SequenceDomain(new WordDomain(['0x', '0X']), new CharacterDomain(str_split('0123456789abcdefABCDEF'), 1, 16))), ['1', '0', '2'], 'number', 'src/tokenize.c:CC_DIGIT'),
            new ValueLexemeGenerator('number', $digits, ['1'], 'number', 'src/parse.y:number'),
            new ValueLexemeGenerator('FLOAT', new ChoiceDomain($decimal, new SequenceDomain(new ChoiceDomain($digits, $decimal), $exponent)), ['1.5', '.5', '1e2'], 'number', 'src/tokenize.c:CC_DIGIT:float'),
            new ValueLexemeGenerator('QNUMBER', new SequenceDomain($digits, new RepeatDomain(new SequenceDomain(new WordDomain(['_']), $digits), 1, 8)), ['1_0'], 'number', 'src/tokenize.c:TK_QNUMBER'),
        );
    }

    /**
     * CC_QUOTE and CC_X: doubled quotes and whole hexadecimal bytes.
     * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/tokenize.c
     */
    public function strings(): LexemeGenerator
    {
        $text = new QuotedDomain("'", alphabet: [...array_map(chr(...), range(1, 127)), 'é', '猫', '😀']);
        return new ChoiceLexemeGenerator(
            new ValueLexemeGenerator('STRING', $text, ["'text'", "'a''b'"], 'string', 'src/tokenize.c:CC_QUOTE'),
            new ValueLexemeGenerator('ids', $text, ["'text'"], 'string', 'src/parse.y:ids'),
            new ValueLexemeGenerator('BLOB', new SequenceDomain(new WordDomain(['X', 'x']), new CharacterDomain(str_split('0123456789abcdefABCDEF'), 0, 127, "'", "'", 2)), ["X'00'", "X''"], 'string', 'src/tokenize.c:CC_X'),
        );
    }
    /**
     * Binds the reviewed keyword dispatch to one exact release.
     * @param array<string, list<string>> $keywords
     */
    public function keywordLexemes(string $version, array $keywords): LexemeGenerator
    {
        return new VersionedLexemeGenerator($version, new VersionCase(
            ['sqlite-3.47.2'],
            new MatchingLexemeGenerator(
                static fn (LexemeInput $input): bool => isset($keywords[$input->terminal()->name]) && !($input->terminal()->name === 'JOIN_KW' && $input->terminal()->within('joinop')),
                new WindowNameLexemeGenerator(new RegisteredLexemeGenerator($keywords, 'tool/mkkeywordhash.c', [])),
            ),
            'sqlite-3.47.2-keywords',
        ));
    }
    /**
     * Fixed spellings beside their parser terminals.
     * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/tool/mkkeywordhash.c
     * @throws RuntimeException When the exact release is unsupported
     * @return array<string, list<string>>
     */
    public function keywords(string $version): array
    {
        SqlVersion::resolve('sqlite', $version);
        return self::KEYWORDS;
    }

    /**
     * @var array<string, list<string>>
     */
    private const KEYWORDS = [
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

}
