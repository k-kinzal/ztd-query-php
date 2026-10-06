<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\GeneratedStorage;

#[CoversClass(GeneratedStorage::class)]
#[Small]
final class GeneratedStorageTest extends TestCase
{
    public function testCasesNameTheTwoStorageKinds(): void
    {
        self::assertSame(['Virtual', 'Stored'], array_column(GeneratedStorage::cases(), 'name'));
    }
}
