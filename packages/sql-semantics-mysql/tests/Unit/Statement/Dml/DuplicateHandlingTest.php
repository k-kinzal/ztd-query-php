<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;

#[CoversClass(DuplicateHandling::class)]
#[Small]
final class DuplicateHandlingTest extends TestCase
{
    public function testCasesSpellReplaceAndIgnore(): void
    {
        self::assertSame(['REPLACE', 'IGNORE'], array_column(DuplicateHandling::cases(), 'value'));
    }
}
