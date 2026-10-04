<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\BooleanTestKind;

#[CoversClass(BooleanTestKind::class)]
#[Small]
final class BooleanTestKindTest extends TestCase
{
    public function testValuesAreTheWrittenTests(): void
    {
        self::assertSame('IS NOT UNKNOWN', BooleanTestKind::IsNotUnknown->value);
    }
}
