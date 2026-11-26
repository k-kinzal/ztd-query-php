<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Header;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderCookie;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderPragmaLowering;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(HeaderPragmaLowering::class)]
#[Small]
final class HeaderPragmaLoweringTest extends TestCase
{
    public function testLowerKeepsAnUnparseableWriteAndIgnoresArgumentsOnReadOnlyFields(): void
    {
        $semantics = new Semantics();
        $writer = $semantics->analyze("PRAGMA user_version(' +7')");
        $read = $semantics->analyze("PRAGMA temp.freelist_count('discarded')");

        self::assertInstanceOf(HeaderPragma::class, $writer);
        self::assertSame(HeaderCookie::UserVersion, $writer->field);
        self::assertSame(0, $writer->value?->value);
        self::assertSame('PRAGMA "main".user_version(0)', $writer->toString());
        self::assertInstanceOf(HeaderPragma::class, $read);
        self::assertNull($read->value);
        self::assertSame('PRAGMA "temp".freelist_count', $read->toString());
    }
}
