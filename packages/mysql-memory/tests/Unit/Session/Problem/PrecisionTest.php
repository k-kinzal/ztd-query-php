<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Session\Problem\Precision;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Precision::class)]
#[Small]
final class PrecisionTest extends TestCase
{
    public function testLegacyWritesTheMessageOfMySql56(): void
    {
        $error = \MySqlMemory\Error\Family\SchemaError::TooBigPrecision->error(9, 'CAST', 6);

        self::assertSame(["Too big precision 9 specified for column 'CAST'. Maximum is 6.", "Too-big precision 9 specified for 'CAST'. Maximum is 6."], [(new Precision())->legacy($error, \SqlSemantics\Contract\GrammarRelease::MySql5651)->getMessage(), (new Precision())->legacy($error, \SqlSemantics\Contract\GrammarRelease::MySql5744)->getMessage()]);
    }
    public function testLegacyPreservesNegativePrecisionInTheVersionedMessage(): void
    {
        $error = \MySqlMemory\Error\Family\SchemaError::TooBigPrecision->error(-1, 'a', 65);

        self::assertSame("Too big precision -1 specified for column 'a'. Maximum is 65.", (new Precision())->legacy($error, \SqlSemantics\Contract\GrammarRelease::MySql5651)->getMessage());
    }
}
