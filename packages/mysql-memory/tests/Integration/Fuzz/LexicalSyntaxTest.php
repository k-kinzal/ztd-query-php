<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class LexicalSyntaxTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $cases = [
            "a'b", "nope 'unterminated", "SELECT 'unterminated", "SELECT 1 + 'bad", "SELECT * FROM 'bad",
            "SELEC 'bad", "SELECT 1; a'b", 'SELECT 1 /* bad', 'SELECT 1 + /*bad', "SELECT @'bad",
            "-- comment\nnope 'bad", "SELECT 'ok', missing ?? 'bad", 'bad `x', 'bad /* comment',
            "bad\n'unclosed", "SELECT 1 ? 'bad", "SELECT ? 'bad", "INSERT INTO t1 VALUES('bad",
        ];
        foreach ($cases as $index => $sql) {
            yield 'query ' . $index => [$sql];
            yield 'prepare ' . $index => ["PREPARE p FROM '" . str_replace("'", "''", $sql) . "'"];
        }
        yield 'unseeded e0e0e0e0e0e0c275' => ["PREPARE _sqlfaker_identifier FROM 'a''b'"];
    }

    #[DataProvider('providerStatements')]
    public function testLexicalFailuresPreserveTheFirstSyntaxError(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
