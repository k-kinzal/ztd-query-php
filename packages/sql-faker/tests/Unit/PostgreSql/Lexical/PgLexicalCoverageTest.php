<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\PostgreSql\Lexical\PgLexicalCoverage;

#[CoversClass(PgLexicalCoverage::class)]
final class PgLexicalCoverageTest extends TestCase
{
    public function testUnitsNameEveryScannerRuleAndEveryParserMode(): void
    {
        $units = (new PgLexicalCoverage())->units(3, ['MODE_TYPE_NAME']);

        self::assertSame(['rule:1', 'rule:2', 'rule:3', 'parser-mode:MODE_TYPE_NAME'], $units);
    }

    public function testWitnessedKeepsTheFirstWitnessThatReachesAUnit(): void
    {
        $terminals = [
            'IDENT' => [
                ['id' => 'first', 'units' => ['rule:1']],
                ['id' => 'second', 'units' => ['rule:1', 'rule:2']],
            ],
        ];

        self::assertSame(['rule:1' => 'first', 'rule:2' => 'second'], (new PgLexicalCoverage())->witnessed($terminals));
    }

    public function testExcludedNamesTheJamRuleTheScannerEndsWith(): void
    {
        $excluded = (new PgLexicalCoverage())->excluded(72);

        self::assertArrayHasKey('rule:72', $excluded);
        self::assertStringContainsString('jam rule', $excluded['rule:72']);
    }

    public function testExcludedNamesTheBranchesThatCanOnlyReportAnError(): void
    {
        $excluded = (new PgLexicalCoverage())->excluded(72);

        self::assertStringContainsString('error-only', $excluded['rule:25']);
    }

    public function testAssertCoveredPassesWhenEveryUnitIsWitnessedOrAccountedFor(): void
    {
        $this->expectNotToPerformAssertions();

        (new PgLexicalCoverage())->assertCovered(['rule:1', 'rule:2'], ['rule:1' => 'w'], ['rule:2' => 'why']);
    }

    public function testAssertCoveredRefusesAUnitNothingReaches(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('misses scanner rules: rule:2');

        (new PgLexicalCoverage())->assertCovered(['rule:1', 'rule:2'], ['rule:1' => 'w'], []);
    }
}
