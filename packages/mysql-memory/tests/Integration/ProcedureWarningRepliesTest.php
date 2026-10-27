<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class ProcedureWarningRepliesTest extends TestCase
{
    #[DataProvider('providerVersions')]
    public function testCallPublishesWarningsInItsFinalCompletion(string $version): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query("CREATE PROCEDURE p() SELECT CAST('x' AS SIGNED)");
        $replies = $session->query('CALL p()');

        self::assertCount(2, $replies);
        self::assertInstanceOf(ResultSet::class, $replies[0]);
        self::assertInstanceOf(Completion::class, $replies[1]);
        self::assertSame([['0']], $replies[0]->rows);
        self::assertSame([0, 1], [$replies[0]->warnings(), $replies[1]->warnings()]);
        self::assertSame([1292], array_column($session->diagnostics->conditions, 1));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerVersions(): iterable
    {
        yield '5.6.51' => ['5.6.51'];
        yield '8.4.7' => ['8.4.7'];
    }
}
