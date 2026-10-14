<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\ContinuingRefusal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContinuingRefusal::class)]
#[Medium]
final class ContinuingRefusalTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function providerInputs(): iterable
    {
        yield 'following warning' => ['SELECT CAST(1 AT LOCAL AS DATE), BINARY 2', [1235, 1287]];
        yield 'repeated refusal' => ['SELECT CAST(1 AT LOCAL AS DATE), CAST(2 AT LOCAL AS CHAR)', [1235, 1235]];
        yield 'earlier collation' => ['SELECT 1 COLLATE missing, CAST(1 AT LOCAL AS DATE)', [1273, 1235]];
        yield 'following abort' => ['SELECT CAST(1 AT LOCAL AS DATE), CONVERT(1 USING missing), BINARY 2', [1235, 1115]];
        yield 'binary abort' => ['SELECT BINARY ((CAST(1 AT LOCAL AS DATE))), BINARY 2', [1235, 1287]];
        yield 'arithmetic continues' => ['SELECT BINARY (CAST(1 AT LOCAL AS DATE) + 1), BINARY 2', [1235, 1287, 1287]];
        yield 'debug refusal' => ['SHOW PARSE_TREE SELECT CAST(1 AT LOCAL AS DATE), BINARY 2', [1235, 1287, 1064]];
        yield 'program statement stops' => ["CREATE PROCEDURE d.p() BEGIN SELECT CAST(1 AT LOCAL AS DATE); SIGNAL SQLSTATE '00000'; END", [1235]];
        yield 'earlier declaration' => ['CREATE PROCEDURE d.p() BEGIN DECLARE x INT; DECLARE x INT; SELECT CAST(1 AT LOCAL AS DATE); END', [1331]];
        yield 'earlier program statement' => ['CREATE PROCEDURE d.p() BEGIN SELECT @@missing; SELECT CAST(1 AT LOCAL AS DATE); END', [1193]];
    }

    /**
     * @param list<int> $codes The complete native condition order
     */
    #[DataProvider('providerInputs')]
    public function testErrorKeepsLaterGrammarConditions(string $sql, array $codes): void
    {
        $session = (new Instance())->connect();
        $reply = $session->run($sql)[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame($codes[0], $reply->getCode());
        self::assertSame($codes, array_column($session->diagnostics->conditions, 1));
    }
}
