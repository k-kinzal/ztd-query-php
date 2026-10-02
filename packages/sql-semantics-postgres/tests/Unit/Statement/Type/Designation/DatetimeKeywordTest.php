<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Designation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DatetimeKeyword;

#[CoversClass(DatetimeKeyword::class)]
#[Small]
final class DatetimeKeywordTest extends TestCase
{
    public function testCasesSpellTheKeywords(): void
    {
        self::assertSame(['TIMESTAMP', 'TIME'], array_map(static fn (DatetimeKeyword $keyword): string => $keyword->value, DatetimeKeyword::cases()));
    }
}
