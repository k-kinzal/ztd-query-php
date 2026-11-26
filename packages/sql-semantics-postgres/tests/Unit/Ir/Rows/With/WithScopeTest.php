<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithRead;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithScope;

#[CoversClass(WithScope::class)]
#[Small]
final class WithScopeTest extends TestCase
{
    public function testWidthChecksTheDeclaredHeading(): void
    {
        $read = new WithRead(0, [new LocalResultColumn(0, new Identifier('result'))]);

        self::assertSame(1, (new WithScope())->width($read, [2], 'pg-17.2/utf8/standard-conforming-strings/native'));
    }
}
