<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Input;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Analysis\Input\CaseLayoutReader;

#[CoversClass(CaseLayoutReader::class)]
#[Small]
final class CaseLayoutReaderTest extends TestCase
{
    public function testOperationRetainsOnlyTheDelimitersAndTheirGaps(): void
    {
        $source = (new SqliteParser())->parse('SELECT case/*base*/1 when 1 then 7/*end*/EnD')->find('expr')[0];
        $layout = (new CaseLayoutReader())->operation($source);
        self::assertSame('case/*base*/2', $layout->start('2'));
        self::assertSame('body/*end*/EnD', $layout->finish('body'));
    }

    public function testArmKeepsEachGapWithItsActualOperandRole(): void
    {
        $source = (new SqliteParser())->parse('SELECT CASE/*when*/when/*test*/1/*then*/then/*result*/7 END')->find('case_exprlist')[0];
        $layout = (new CaseLayoutReader())->arm($source);
        self::assertSame('when/*test*/2/*then*/then/*result*/8', $layout->write('2', '8'));
        self::assertSame('CASE/*when*/when 2 then 8', $layout->append('CASE', 'when 2 then 8'));
    }

    public function testOtherwiseRetainsItsActualDelimiterAndGaps(): void
    {
        $source = (new SqliteParser())->parse('SELECT CASE WHEN 1 THEN 2/*else*/else/*result*/3 END')->find('case_else')[0];
        $layout = (new CaseLayoutReader())->otherwise($source);
        self::assertSame('body/*else*/else/*result*/4', $layout->append('body', '4'));
        self::assertSame('body ELSE 4', (new CaseLayoutReader())->otherwise(null)->append('body', '4'));
    }
}
