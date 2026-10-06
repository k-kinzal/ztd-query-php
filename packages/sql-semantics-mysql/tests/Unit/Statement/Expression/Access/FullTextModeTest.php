<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextMode;

#[CoversClass(FullTextMode::class)]
#[Small]
final class FullTextModeTest extends TestCase
{
    public function testCasesAreTheThreeSearchModes(): void
    {
        self::assertSame(['NaturalLanguage', 'QueryExpansion', 'Boolean'], array_column(FullTextMode::cases(), 'name'));
    }
}
