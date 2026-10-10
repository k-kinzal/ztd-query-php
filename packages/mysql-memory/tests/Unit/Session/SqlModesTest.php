<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\SqlModes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(SqlModes::class)]
#[Small]
final class SqlModesTest extends TestCase
{
    public function testParseReadsNamesWithoutRegardToCase(): void
    {
        self::assertSame('ANSI_QUOTES,STRICT_TRANS_TABLES', SqlModes::parse('strict_trans_tables,ansi_quotes')?->toString());
    }

    public function testParseSkipsEmptyNamesAndSpaces(): void
    {
        self::assertSame('STRICT_ALL_TABLES', SqlModes::parse(' , strict_all_tables ,')?->toString());
        self::assertSame('', SqlModes::parse('')?->toString());
    }

    public function testParseRefusesAnUnknownName(): void
    {
        self::assertNull(SqlModes::parse('STRICT_TRANS_TABLES,NOSUCH'));
        self::assertNull(SqlModes::parse('NOT_USED'));
        self::assertNull(SqlModes::parse('NOT_USED_9'));
    }

    public function testParseRefusesAnUnknownNameOfAStatement(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1231);
        $this->expectExceptionMessage("Variable 'sql_mode' can't be set to the value of 'NOSUCH'");

        $session->query("SET sql_mode = 'NOSUCH'");
    }

    public function testHasTellsWhetherTheSetHoldsAMode(): void
    {
        $modes = new SqlModes(['ANSI_QUOTES']);

        self::assertTrue($modes->has('ANSI_QUOTES'));
        self::assertFalse($modes->has('ONLY_FULL_GROUP_BY'));
    }

    public function testHasHoldsTheModesOfACombination(): void
    {
        $modes = new SqlModes(['ANSI']);

        self::assertTrue($modes->has('ANSI'));
        self::assertTrue($modes->has('PIPES_AS_CONCAT'));
        self::assertTrue($modes->has('ONLY_FULL_GROUP_BY'));
        self::assertFalse($modes->has('STRICT_TRANS_TABLES'));
    }

    public function testStrictTellsWhetherAStrictModeIsSet(): void
    {
        self::assertTrue((new SqlModes(['STRICT_TRANS_TABLES']))->strict());
        self::assertTrue((new SqlModes(['STRICT_ALL_TABLES']))->strict());
        self::assertTrue((new SqlModes(['TRADITIONAL']))->strict());
        self::assertFalse((new SqlModes(['ANSI']))->strict());
    }

    public function testToStringWritesTheModesInTheOrderOfTheServer(): void
    {
        self::assertSame('REAL_AS_FLOAT,PIPES_AS_CONCAT,ANSI_QUOTES,IGNORE_SPACE,ONLY_FULL_GROUP_BY,ANSI', (new SqlModes(['ANSI']))->toString());
        self::assertSame('STRICT_TRANS_TABLES,STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,TRADITIONAL,NO_ENGINE_SUBSTITUTION', (new SqlModes(['TRADITIONAL']))->toString());
    }

    public function testToStringOfTheDefault(): void
    {
        self::assertSame(SqlModes::DEFAULT, SqlModes::parse(SqlModes::DEFAULT)?->toString());
        self::assertSame('ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION', SqlModes::DEFAULT);
    }

    public function testToStringOfAStatement(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode = 'traditional'");
        $result = $session->query('SELECT @@sql_mode')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['STRICT_TRANS_TABLES,STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,TRADITIONAL,NO_ENGINE_SUBSTITUTION']], $result->rows);
    }

    public function testNamesAnswerTheModesOfTheRelease(): void
    {
        self::assertContains('NO_AUTO_CREATE_USER', SqlModes::names(GrammarRelease::MySql5744));
        self::assertNotContains('NO_AUTO_CREATE_USER', SqlModes::names(GrammarRelease::MySql847));
        self::assertNotContains('TIME_TRUNCATE_FRACTIONAL', SqlModes::names(GrammarRelease::MySql5651));
    }

    public function testCombinationsAddOnlyFullGroupByToAnsiFrom57On(): void
    {
        self::assertNotContains('ONLY_FULL_GROUP_BY', SqlModes::combinations(GrammarRelease::MySql5651)['ANSI']);
        self::assertContains('ONLY_FULL_GROUP_BY', SqlModes::combinations(GrammarRelease::MySql5744)['ANSI']);
        self::assertSame(['HIGH_NOT_PRECEDENCE'], SqlModes::combinations(GrammarRelease::MySql5744)['MYSQL40']);
    }

    public function testParseReadsTheModesOf57(): void
    {
        self::assertSame('STRICT_TRANS_TABLES,STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,TRADITIONAL,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION', SqlModes::parse('traditional', GrammarRelease::MySql5744)?->toString());
        self::assertNull(SqlModes::parse('NO_AUTO_CREATE_USER'));
        self::assertTrue(SqlModes::parse('STRICT_TRANS_TABLES,NO_AUTO_CREATE_USER', GrammarRelease::MySql5744)?->strict());
    }
}
