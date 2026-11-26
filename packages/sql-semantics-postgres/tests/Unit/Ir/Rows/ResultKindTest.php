<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\ResultKind;

#[CoversClass(ResultKind::class)]
#[Small]
final class ResultKindTest extends TestCase
{
    public function testIdentifiesTheSignedIntegerOutputRole(): void
    {
        self::assertSame('int32', ResultKind::Integer->value);
    }
}
