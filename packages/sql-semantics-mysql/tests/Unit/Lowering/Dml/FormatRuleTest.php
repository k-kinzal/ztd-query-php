<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Dml\FormatRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(FormatRule::class)]
#[Medium]
final class FormatRuleTest extends TestCase
{
    public function testCharsetLowersBothSpellings(): void
    {
        self::assertSame('SELECT 1 INTO OUTFILE \'f\' CHARSET DEFAULT', (new Inspection('mysql-5.6.51'))->inspect('select 1 into outfile \'f\' char set default')->toString());
        self::assertSame('SELECT 1 INTO OUTFILE \'f\' CHARSET latin1', (new Inspection())->inspect('select 1 into outfile \'f\' charset latin1')->toString());
    }

    public function testFieldsLowersEveryOption(): void
    {
        self::assertSame('SELECT 1 INTO OUTFILE \'f\' COLUMNS TERMINATED BY \',\' OPTIONALLY ENCLOSED BY \'"\' ESCAPED BY x\'5c\'', (new Inspection())->inspect('select 1 into outfile \'f\' columns terminated by \',\' optionally enclosed by \'"\' escaped by x\'5c\'')->toString());
    }

    public function testLinesLowersEveryOption(): void
    {
        self::assertSame('SELECT 1 INTO OUTFILE \'f\' LINES STARTING BY \'>\' TERMINATED BY \';\'', (new Inspection())->inspect('select 1 into outfile \'f\' lines starting by \'>\' terminated by \';\'')->toString());
    }
}
