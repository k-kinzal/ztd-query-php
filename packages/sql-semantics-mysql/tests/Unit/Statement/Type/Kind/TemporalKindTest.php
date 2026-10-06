<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;

#[CoversClass(TemporalKind::class)]
#[Small]
final class TemporalKindTest extends TestCase
{
    public function testCasesSpellTheKeywordOfEveryDateAndTimeType(): void
    {
        self::assertSame(['Date', 'Time', 'Timestamp', 'DateTime', 'Year'], array_column(TemporalKind::cases(), 'name'));
        self::assertSame(['DATE', 'TIME', 'TIMESTAMP', 'DATETIME', 'YEAR'], array_column(TemporalKind::cases(), 'value'));
    }
}
