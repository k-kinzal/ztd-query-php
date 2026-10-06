<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Designation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DecimalKeyword;

#[CoversClass(DecimalKeyword::class)]
#[Small]
final class DecimalKeywordTest extends TestCase
{
    public function testCasesSpellTheNumericType(): void
    {
        self::assertSame(['NUMERIC', 'DECIMAL', 'DEC'], array_map(static fn (DecimalKeyword $keyword): string => $keyword->value, DecimalKeyword::cases()));
    }
}
