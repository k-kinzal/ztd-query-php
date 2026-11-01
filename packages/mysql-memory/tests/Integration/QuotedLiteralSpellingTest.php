<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversNothing]
#[Medium]
final class QuotedLiteralSpellingTest extends TestCase
{
    #[DataProvider('providerStrings')]
    public function testDecodedOperandsPreserveQuotationAndExecuteWithoutSql(string $version, string $literal, string $bytes, string $mode): void
    {
        $session = (new Instance($version))->connect();
        $session->query("SET sql_mode = '$mode'");
        $sql = 'SELECT ' . $literal;
        $statement = $session->analyze($sql);
        self::assertSame($sql, $statement->toString());
        $session->text = 'No executable SQL is available';
        $reply = (new Execution($session))->statement($statement);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$bytes]], $reply->rows);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function providerStrings(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if (!str_starts_with($release->value, 'mysql-')) {
                continue;
            }
            $version = substr($release->value, 6);
            foreach ([
                'raw control' => ["'\x1a'", "\x1a", ''],
                'escaped control' => ["'\\Z'", "\x1a", ''],
                'mixed controls' => ["'\x1a\\Z\n\\n'", "\x1a\x1a\n\n", ''],
                'double quotes' => ['"a\\"b"', 'a"b', ''],
                'adjacent segments' => ["'\x1a' '\\Z'", "\x1a\x1a", ''],
                'pattern escape' => ["'\\%\\\\%'", '\\%\\%', ''],
                'discarded backslash' => ["'\\q'", 'q', ''],
                'no escapes' => ["'\\Z\\q'", '\\Z\\q', 'NO_BACKSLASH_ESCAPES'],
            ] as $name => [$literal, $bytes, $mode]) {
                yield "$version $name" => [$version, $literal, $bytes, $mode];
            }
        }
    }

    public function testHelpRetainsTheRawControlOperandFromTheFuzzer(): void
    {
        $session = (new Instance())->connect();
        $sql = "HELP '>\x1a" . str_repeat("\x01", 175) . "'";
        $statement = $session->analyze($sql);
        self::assertSame($sql, $statement->toString());
    }
}
