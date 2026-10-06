<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Hint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\SamplingMethod;

#[CoversClass(SamplingMethod::class)]
#[Small]
final class SamplingMethodTest extends TestCase
{
    public function testCasesSpellTheKeywords(): void
    {
        self::assertSame(['SYSTEM', 'BERNOULLI'], array_column(SamplingMethod::cases(), 'value'));
    }
}
