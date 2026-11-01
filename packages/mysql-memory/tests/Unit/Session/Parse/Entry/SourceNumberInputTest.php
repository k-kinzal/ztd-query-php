<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry;

use MySqlMemory\Session\Parse\Entry\SourceNumberInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;

#[CoversClass(SourceNumberInput::class)]
#[Small]
final class SourceNumberInputTest extends TestCase
{
    public function testFileCountReportsTheOriginalLineAndSuffix(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $prefix = "LOAD DATA INFILE 'f'\nCOUNT ";
        $session->text = $prefix . '000 INTO TABLE t';
        $problem = new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\ZeroFileCount(strlen($prefix));
        $error = (new SourceNumberInput())->fileCount($problem, $session);

        self::assertSame(1064, $error->getCode());
        self::assertSame("The number of files cannot be zero near '000 INTO TABLE t' at line 2", $error->getMessage());
    }

    public function testErrorUsesTheRetainedNumericSpelling(): void
    {
        $problem = new RefusedSourceNumber(SourceOptionKind::RequireRowFormat, new Numeral('100000000', true), 0);
        $error = (new SourceNumberInput())->error($problem, 'unrelated invalid input');

        self::assertSame(3945, $error->getCode());
        self::assertStringContainsString('0x100000000', $error->getMessage());
    }

    public function testErrorLocatesTheRefusedOccurrenceOnItsInputLine(): void
    {
        $sql = "CHANGE REPLICATION SOURCE TO GTID_ONLY=0,\nGTID_ONLY=2 TRAILING";
        $problem = new RefusedSourceNumber(SourceOptionKind::GtidOnly, new Numeral('2'), 52);
        $error = (new SourceNumberInput())->error($problem, $sql);

        self::assertSame(1064, $error->getCode());
        self::assertSame("You have an error in your CHANGE REPLICATION SOURCE syntax; GTID_ONLY only accepts values 0 or 1 near '2 TRAILING' at line 2", $error->getMessage());
    }
}
