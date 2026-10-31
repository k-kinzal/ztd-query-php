<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class HintSyntaxDiagnosticsTest extends TestCase
{
    public function testEachCommentKeepsItsWarningOnTheAnalyzedStatement(): void
    {
        $session = (new Instance())->connect();
        $sql = "SELECT /*+ BKA(t) FOO */ 1 FROM (SELECT /*+ */ 1) d\nWHERE 1 IN (SELECT /*+ MAX_EXECUTION_TIME(4294967296 ) */ 1)";

        $statement = $session->analyze($sql);
        $warnings = array_filter($statement->statementInformation()->warnings, static fn ($warning): bool => $warning instanceof \SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError);
        self::assertSame([
            ["Optimizer hint syntax error near 'FOO */ 1 FROM (SELECT /*+ */ 1) d\nWHERE 1 IN (SELECT /*+ MAX_EXECUTION_TIME(4294' at line 1", true],
            ["Optimizer hint syntax error near '*/' at line 1", false],
            ["Unsupported MAX_EXECUTION_TIME near ') */ 1)' at line 2", false],
        ], array_map(static fn ($warning): array => [$session->preparation->hintMessages[$warning] ?? null, $warning->leading], array_values($warnings)));
    }

    public function testLeadingHintWarningsPrecedeOtherInputNotices(): void
    {
        $session = (new Instance())->connect();
        $session->query('SELECT SQL_NO_CACHE 1 FROM (SELECT /*+ FOO */ 1) d');
        $first = array_column($session->diagnostics->conditions, 1);
        $session->query('SELECT /*+ FOO */ SQL_NO_CACHE 1');

        self::assertSame([[1681, 1064], [1064, 1681]], [$first, array_column($session->diagnostics->conditions, 1)]);
    }

    public function testMysql56DoesNotInterpretHintComments(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('SELECT /*+ FOO */ 1');

        self::assertSame([], $session->diagnostics->conditions);
    }

}
