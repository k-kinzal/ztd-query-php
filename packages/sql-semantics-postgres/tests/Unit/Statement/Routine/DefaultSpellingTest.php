<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\DefaultSpelling;

#[CoversClass(DefaultSpelling::class)]
#[Small]
final class DefaultSpellingTest extends TestCase
{
    public function testCasesSpellTheIntroducer(): void
    {
        self::assertSame(['DEFAULT', '='], [DefaultSpelling::Keyword->value, DefaultSpelling::EqualsSign->value]);
    }
}
