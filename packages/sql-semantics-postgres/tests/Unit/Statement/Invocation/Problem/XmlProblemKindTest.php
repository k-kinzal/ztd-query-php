<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\XmlProblemKind;

#[CoversClass(XmlProblemKind::class)]
#[Small]
final class XmlProblemKindTest extends TestCase
{
    public function testCasesCarryTheServerMessages(): void
    {
        self::assertSame('unnamed XML element value must be a column reference', XmlProblemKind::UnnamedElement->value);
    }
}
