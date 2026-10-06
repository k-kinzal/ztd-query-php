<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\TrimSide;

#[CoversClass(TrimSide::class)]
#[Small]
final class TrimSideTest extends TestCase
{
    public function testFunctionAnswersTheFunctionCalled(): void
    {
        self::assertSame(['btrim', 'ltrim', 'rtrim'], [TrimSide::Both->function(), TrimSide::Leading->function(), TrimSide::Trailing->function()]);
    }
}
