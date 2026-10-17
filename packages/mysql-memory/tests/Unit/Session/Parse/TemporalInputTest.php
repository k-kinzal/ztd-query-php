<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\TemporalInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(TemporalInput::class)]
#[Medium]
final class TemporalInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function providerConditions(): iterable
    {
        yield 'local cast follows literal' => ["SELECT TIMESTAMP 'text', CAST(1 AT LOCAL AS CHAR)", [1235]];
        yield 'local cast contains literal' => ["SELECT CAST(TIMESTAMP 'text' AT LOCAL AS CHAR)", [1235]];
        yield 'two continuing refusals' => ["SELECT CAST(1 AT LOCAL AS CHAR), DATE 'text', CAST(2 AT LOCAL AS CHAR)", [1235, 1235]];
        yield 'collation after literal' => ["SELECT DATE 'text', 1 COLLATE missing", [1273]];
        yield 'precision after literal' => ["SELECT DATE 'text', CAST(1 AS DATETIME(7))", [1426]];
        yield 'warnings surround literal' => ["SELECT !1, DATE 'text', BINARY 1", [1287, 1287, 1525]];
        yield 'assignment after literal' => ["SELECT DATE 'text', @a:=1", [1287, 1525]];
    }

    public function testNoticesKeepsOperatorWarningsBeforeARefusedCast(): void
    {
        $session = (new Instance())->connect();
        $sql = "SELECT !1, DATE 'bad', CAST(1 AT LOCAL AS CHAR)";
        $parser = $session->semantics()->parser();
        self::assertInstanceOf(\SqlParser\Parser\ReductionParser::class, $parser);
        $notices = (new TemporalInput())->notices($parser, $sql, strlen($sql), $session);

        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BangNot, $notices[0][1]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $notices[1][1]);
    }

    /**
     * @param list<int> $codes The ordered conditions before contextual value interpretation
     */
    #[DataProvider('providerConditions')]
    public function testCheckPreservesCompletedGrammarConditions(string $sql, array $codes): void
    {
        $session = (new Instance('8.4.7'))->connect();

        $session->run($sql);

        self::assertSame($codes, array_column($session->diagnostics->conditions, 1));
    }

    public function testCheckRetainsLegacyInputOrder(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $session->run("SELECT DATE 'text', 1 COLLATE missing");

        self::assertSame([1525], array_column($session->diagnostics->conditions, 1));
    }

    public function testBeforeFindsACompletedLegacyLiteralBeforeInvalidSyntax(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $found = (new TemporalInput())->before("SELECT DATE 'bad', CAST(1 AT LOCAL AS CHAR)", 40, $session);

        self::assertNotNull($found);
        self::assertSame([7, 1525], [$found[0], $found[1]->getCode()]);
        self::assertNull((new TemporalInput())->before("SELECT DATE 'bad'", 7, $session));
    }

    public function testBeforeInterpretsACompletedProgramStatementBeforeLaterInvalidSyntax(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $sql = "CREATE PROCEDURE d.p() BEGIN SELECT DATE 'bad'; PARTITION BROKEN; END";
        $found = (new TemporalInput())->before($sql, strlen($sql), $session);

        self::assertNotNull($found);
        self::assertSame(1525, $found[1]->getCode());
        self::assertNull((new TemporalInput())->before("SELECT DATE 'bad', broken syntax", 1000, $session));
    }
}
