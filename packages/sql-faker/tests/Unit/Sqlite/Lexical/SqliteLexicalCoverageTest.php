<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Sqlite\Lexical\SqliteLexicalCoverage;

#[CoversClass(SqliteLexicalCoverage::class)]
final class SqliteLexicalCoverageTest extends TestCase
{
    public function testWitnessedKeepsTheFirstSampleThatReachesAClass(): void
    {
        $witnessed = (new SqliteLexicalCoverage())->witnessed([
            'first' => ['a', [], ['CC_ID']],
            'second' => ['b', [], ['CC_ID', 'CC_SPACE']],
        ]);

        self::assertSame(['CC_ID' => 'first', 'CC_SPACE' => 'second'], $witnessed);
    }

    public function testAssertClassifiedPassesWhenEveryDeclaredClassIsAccountedFor(): void
    {
        $this->expectNotToPerformAssertions();

        (new SqliteLexicalCoverage())->assertClassified(
            ['CC_ID', 'CC_SPACE'],
            ['CC_ID' => 'first'],
            ['CC_SPACE' => 'why'],
        );
    }

    public function testAssertClassifiedRefusesADeclaredClassNothingReaches(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('misses character classes: CC_SPACE');

        (new SqliteLexicalCoverage())->assertClassified(['CC_ID', 'CC_SPACE'], ['CC_ID' => 'first'], []);
    }

    public function testAssertClassifiedRefusesAClassTheSourceDoesNotDeclare(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('references unknown character classes: CC_GONE');

        (new SqliteLexicalCoverage())->assertClassified(['CC_ID'], ['CC_ID' => 'first', 'CC_GONE' => 'x'], []);
    }
}
