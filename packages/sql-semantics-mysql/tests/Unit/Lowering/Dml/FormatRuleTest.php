<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dml\FormatRule;

#[CoversClass(FormatRule::class)]
#[Medium]
final class FormatRuleTest extends TestCase
{
    public function testCharsetLowersBothSpellings(): void
    {
        self::assertSame('SELECT 1 INTO OUTFILE \'f\' CHAR SET DEFAULT', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 into outfile \'f\' char set default')->toString());
        self::assertSame('SELECT 1 INTO OUTFILE \'f\' CHARSET latin1', (new Semantics(Dialect::MySql))->analyze('select 1 into outfile \'f\' charset latin1')->toString());
    }

    #[DataProvider('providerCharacterSetForms')]
    public function testCharsetFormRetainsLoadAndOutputSpellings(string $version, string $sql): void
    {
        self::assertSame($sql, (new Semantics(Dialect::MySql, 'mysql-' . $version))->analyze($sql)->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerCharacterSetForms(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['CHARSET', 'CHAR SET', 'CHARACTER SET'] as $form) {
                foreach (["LOAD DATA INFILE 'f' INTO TABLE t", "SELECT 1 INTO OUTFILE 'f'"] as $prefix) {
                    yield $version . ' ' . $prefix . ' ' . $form => [$version, $prefix . ' ' . $form . ' utf8mb4'];
                }
            }
        }
    }

    public function testFieldsLowersEveryOption(): void
    {
        self::assertSame('SELECT 1 INTO OUTFILE \'f\' COLUMNS TERMINATED BY \',\' OPTIONALLY ENCLOSED BY \'"\' ESCAPED BY x\'5c\'', (new Semantics(Dialect::MySql))->analyze('select 1 into outfile \'f\' columns terminated by \',\' optionally enclosed by \'"\' escaped by x\'5c\'')->toString());
    }

    public function testLinesLowersEveryOption(): void
    {
        self::assertSame('SELECT 1 INTO OUTFILE \'f\' LINES STARTING BY \'>\' TERMINATED BY \';\'', (new Semantics(Dialect::MySql))->analyze('select 1 into outfile \'f\' lines starting by \'>\' terminated by \';\'')->toString());
    }
}
