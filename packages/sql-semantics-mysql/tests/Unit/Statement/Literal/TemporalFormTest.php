<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;

#[CoversClass(TemporalForm::class)]
#[Small]
final class TemporalFormTest extends TestCase
{
    public function testCasesSpellTheKeywordOfEachForm(): void
    {
        self::assertSame(['DATE', 'TIME', 'TIMESTAMP'], array_column(TemporalForm::cases(), 'value'));
    }
}
