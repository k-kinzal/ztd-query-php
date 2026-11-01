<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Special;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Special\Digests;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Digests::class)]
#[Small]
final class DigestsTest extends TestCase
{
    public function testRoutinesNamesTheDigestFunctions(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Digests())->routines());

        self::assertSame(['STATEMENT_DIGEST', 'STATEMENT_DIGEST_TEXT'], $names);
    }

    public function testTokensDigestAStatementAsTheServerDoes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $result = $session->query("SELECT STATEMENT_DIGEST('select 1'), STATEMENT_DIGEST_TEXT('select a, b from t where c = ''x'' and d in (1, 2, 3) order by a limit 10'), STATEMENT_DIGEST('select a, b from t where c = ''x'' and d in (1, 2, 3) order by a limit 10'), STATEMENT_DIGEST_TEXT(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['d1b44b0c19af710b5a679907e284acd2ddc285201794bc69a2389d77baedddae', 'SELECT `a` , `b` FROM `t` WHERE `c` = ? AND `d` IN (...) ORDER BY `a` LIMIT ?', '06cffe9932a627ff9b6f07010cc68d024d79f2079ea9c94f3f6674af377f0474', null]], $result->rows);
        self::assertSame([[Field::VarString, 256], [Field::LongBlob, 268435456], [Field::LongBlob, 16777216]], [[$result->columns[0]->type, $result->columns[0]->length], [$result->columns[1]->type, $result->columns[1]->length], [$result->columns[3]->type, $result->columns[3]->length]]);
    }

    public function testTokensRefuseAWideCharacterSet(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("The function statement_digest_text does not support the character set 'utf16_general_ci'.");

        (new Instance())->connect()->query("SELECT STATEMENT_DIGEST_TEXT(_utf16'select 1')");
    }

    public function testParseRefusesASyntaxError(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Could not parse argument to digest function: "You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'selec 1\' at line 1".');

        (new Instance())->connect()->query("SELECT STATEMENT_DIGEST('selec 1')");
    }

    public function testParseRefusesAnEmptyText(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Could not parse argument to digest function: "Query was empty".');

        (new Instance())->connect()->query("SELECT STATEMENT_DIGEST_TEXT('  ')");
    }

    public function testParseRefusesATableWithoutDatabase(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Could not parse argument to digest function: "No database selected".');

        (new Instance())->connect()->query("SELECT STATEMENT_DIGEST_TEXT('select * from t')");
    }

    public function testUnqualifiedExemptsCommonTableExpressions(): void
    {
        $parser = new MySqlParser('mysql-8.4.7');

        self::assertSame([false, true, false, true], [(new Digests())->unqualified($parser->parse('with c as (select 1) select * from c')), (new Digests())->unqualified($parser->parse('show tables')), (new Digests())->unqualified($parser->parse('select * from d.t')), (new Digests())->unqualified($parser->parse('grant select on t to u'))]);
    }

    public function testDigestReducesValuesRowsAndSigns(): void
    {
        $parser = new MySqlParser('mysql-8.4.7');
        $digests = new Digests();

        self::assertSame([
            'INSERT INTO `t` VALUES (...) /* , ... */',
            'SELECT ?, ... , - ?, ... = - ?',
            'SELECT ? FROM `t` WHERE `a` IS NULL AND `b` IN ( SELECT ? )',
            'SELECT /*+ BKA ( `t1`@`qb` ) MAX_EXECUTION_TIME (?) */ DISTINCTROW @? , @@SESSION . `sql_mode` FROM DUAL',
        ], [
            $digests->bytes($digests->digest($parser->parse('insert into t values (1, 2), (3, 4)'), 'mysql-8.4.7', Charset::known('utf8mb4')))[1],
            $digests->bytes($digests->digest($parser->parse("select 1, -2, -'x', 1 = -1"), 'mysql-8.4.7', Charset::known('utf8mb4')))[1],
            $digests->bytes($digests->digest($parser->parse('select null from t where a is null and b in (select -1)'), 'mysql-8.4.7', Charset::known('utf8mb4')))[1],
            $digests->bytes($digests->digest($parser->parse('select /*+ BKA(t1@qb) MAX_EXECUTION_TIME(1000) */ distinct @a, @@session.sql_mode from dual'), 'mysql-8.4.7', Charset::known('utf8mb4')))[1],
        ]);
    }

    public function testHintStoresTheHintBetweenItsDelimiters(): void
    {
        $stored = [];
        (new Digests())->hint($stored, ' BKA(t) ', Charset::known('utf8mb4'));

        self::assertSame([1108, 1002, 40, 1106, 41, 1109], array_column($stored, 0));
    }

    public function testCollectAnswersEachTokenWithItsRule(): void
    {
        $tokens = [];
        (new Digests())->collect((new MySqlParser('mysql-8.4.7'))->parse('select null'), $tokens);

        self::assertSame([['SELECT_SYM', 'NULL_SYM'], 'null_as_literal'], [array_map(static fn (array $token): string => $token[0]->name, $tokens), $tokens[1][1]]);
    }

    public function testNumberOffsetsTheTerminalsOfTheGrammar(): void
    {
        self::assertSame([287, 748, 1151], [Digests::number(31), Digests::number(491), Digests::number(744)]);
    }

    public function testSpellingsAnswerTheLastSpellingOfEachKeyword(): void
    {
        $spellings = (new Digests())->spellings('mysql-8.4.7');

        self::assertSame(['DISTINCTROW', 'INTEGER', 'NOW', 'SYSTEM_USER'], [$spellings['DISTINCT'], $spellings['INT_SYM'], $spellings['NOW_SYM'], $spellings['USER']]);
    }

    public function testValueFoldsASignAfterAnOperator(): void
    {
        $stored = [[748, '', 'SELECT', 'SELECT_SYM'], [45, '', '-', '-']];
        (new Digests())->value($stored, true);

        self::assertSame([[748, '', 'SELECT', 'SELECT_SYM'], [1100, '', '?', '']], $stored);
    }

    public function testAddReducesInAndRows(): void
    {
        $stored = [[504, '', 'IN', 'IN_SYM'], [40, '', '(', '('], [1101, '', '?, ...', '']];
        (new Digests())->add($stored, 41, ')', ')');

        self::assertSame([[1110, '', 'IN (...)', '']], $stored);
    }

    public function testBytesKeepAtMost1024Bytes(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $digests = new Digests();

        self::assertSame(['SELECT', 2], [$digests->bytes($digests->digest($digests->parse($frame, 'select a' . str_repeat('x', 1100)), 'mysql-8.4.7', Charset::known('utf8mb4')))[1], strlen($digests->bytes($digests->digest($digests->parse($frame, 'select a' . str_repeat('x', 1100)), 'mysql-8.4.7', Charset::known('utf8mb4')))[0])]);
    }
}
