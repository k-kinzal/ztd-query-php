<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\CallMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\CallMisuseKind;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(CallMisuse::class)]
#[Small]
final class CallMisuseTest extends TestCase
{
    public function testMessageNamesTheFunction(): void
    {
        self::assertSame('upper(*) specified, but upper is not an aggregate function', (new CallMisuse(CallMisuseKind::StarOnPlainFunction, new Name('upper')))->message());
    }
}
