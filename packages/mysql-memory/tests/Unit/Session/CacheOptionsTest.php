<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Error\StatementError;
use MySqlMemory\Instance;
use MySqlMemory\Session\CacheOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

#[CoversClass(CacheOptions::class)]
#[Small]
final class CacheOptionsTest extends TestCase
{
    public function testReportedRefusesTwoModifiersBeforeASyntaxErrorIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $answers = $session->run('SELECT SQL_CACHE SQL_CACHE');

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame("Option 'SQL_CACHE' used twice in statement", $answers[0]->getMessage());
        self::assertSame([['Warning', 1681, "'SQL_CACHE' is deprecated and will be removed in a future release."], ['Warning', 1681, "'SQL_CACHE' is deprecated and will be removed in a future release."], ['Error', 1225, "Option 'SQL_CACHE' used twice in statement"]], $session->diagnostics->conditions);
    }

    public function testReportedKeepsTheWarningsBeforeASyntaxErrorIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->run('SELECT SQL_NO_CACHE 1 FROM DUAL WHERE (');

        self::assertSame([1681, 1064], array_column($session->diagnostics->conditions, 1));
    }

    public function testReportedNamesTheClauseAUnionOperandMayNotWrite(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $answers = $session->run('SELECT 1 ORDER BY 1 UNION SELECT 2');

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame([1221, 'Incorrect usage of UNION and ORDER BY'], [$answers[0]->getCode(), $answers[0]->getMessage()]);
    }

    public function testNoticesAnswerTheWarningsOfTheTokensBeforeAnOffset(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $tokens = $session->semantics()->parser()->tokenize('INSERT DELAYED INTO t SELECT SQL_NO_CACHE 1 FROM t PROCEDURE ANALYSE()');
        [$warned, $conflict] = (new CacheOptions())->notices($tokens, 1000, GrammarRelease::MySql5744);

        self::assertSame([7 => 'INSERT DELAYED is no longer supported. The statement was converted to INSERT.', 29 => "'SQL_NO_CACHE' is deprecated and will be removed in a future release.", 61 => "'PROCEDURE ANALYSE' is deprecated and will be removed in a future release."], array_map(static fn ($construct): string => $construct->value, $warned));
        self::assertNull($conflict);
    }

    public function testDeprecatedAnswersTheDeprecatedConstructOfATokenInTheWordsOfTheRelease(): void
    {
        $options = new CacheOptions();

        self::assertSame(
            [Deprecated::DelayedInsert, Deprecated::InsertDelayed, Deprecated::ReplaceDelayed, Deprecated::ProcedureAnalyse, null],
            [
                $options->deprecated('DELAYED_SYM', 'INSERT', GrammarRelease::MySql5651),
                $options->deprecated('DELAYED_SYM', 'INSERT', GrammarRelease::MySql5744),
                $options->deprecated('DELAYED_SYM', 'REPLACE', GrammarRelease::MySql5744),
                $options->deprecated('ANALYSE_SYM', 'PROCEDURE_SYM', GrammarRelease::MySql5651),
                $options->deprecated('DELAYED_SYM', 'SELECT_SYM', GrammarRelease::MySql5744),
            ],
        );
    }

    public function testUsageReadsTheWrongUsageOfARefusedStatement(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $answers = $session->run('SELECT 1 INTO @a UNION SELECT 2');

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame('Incorrect usage of UNION and INTO', $answers[0]->getMessage());
        self::assertNull((new CacheOptions())->usage(StatementError::ParseError->error('', 1)));
    }

    public function testPlacedPrefersARefusedPlacementOverAConflictIn56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $answers = $session->run('SELECT 1 FROM (SELECT SQL_CACHE SQL_CACHE 1) x');

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame([1234, "Incorrect usage/placement of 'SQL_CACHE'"], [$answers[0]->getCode(), $answers[0]->getMessage()]);
        self::assertNull((new CacheOptions())->placed(new \SqlSemantics\Platform\MySql\Statement\Query\Problem\CacheOptionConflict(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Cache, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Cache), $session->analyze('SELECT 1')->statement, $session));
    }
}
