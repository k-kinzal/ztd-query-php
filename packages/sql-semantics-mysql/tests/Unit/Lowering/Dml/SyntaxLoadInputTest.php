<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dml\SyntaxLoadInput;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\ZeroFileCount;

#[CoversClass(SyntaxLoadInput::class)]
#[Small]
final class SyntaxLoadInputTest extends TestCase
{
    public function testRejectedRetainsOnlyTheZeroCountInputPosition(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze("LOAD DATA INFILE 'f' COUNT 000 INTO TABLE t");
            self::fail('MySQL 8.4 requires a positive file count.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(ZeroFileCount::class, $failure->problem);
            self::assertSame(27, $failure->problem->offset);
            self::assertSame(30, $failure->end);
            self::assertTrue($failure->aborts);
        }
    }

    public function testRejectedAllowsZeroInTheEarlierReleaseWithTheSameProduction(): void
    {
        $statement = (new Semantics(Dialect::MySql, 'mysql-8.2.0'))->analyze("LOAD DATA INFILE 'f' COUNT 000 INTO TABLE t")->statement;
        self::assertInstanceOf(LoadTable::class, $statement);
        self::assertSame('000', $statement->input->count?->text);
    }
}
