<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameMark;

#[CoversClass(RenameMark::class)]
#[Small]
final class RenameMarkTest extends TestCase
{
    public function testCasesRetainTheWrittenAlternatives(): void
    {
        self::assertSame(['TO', 'AS', '=', ''], array_column(RenameMark::cases(), 'value'));
    }
}
