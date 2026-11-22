<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Rows\ResultType;

#[CoversClass(ResultType::class)]
#[Small]
final class ResultTypeTest extends TestCase
{
    public function testIdentifiesTheSignedIntegerOutputRole(): void
    {
        self::assertSame('int32', ResultType::Integer->value);
    }
}
