<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyConflict;

#[CoversClass(KeyConflict::class)]
#[Small]
final class KeyConflictTest extends TestCase
{
    public function testContainsTheFiveNativeAlgorithms(): void
    {
        self::assertSame(['ABORT', 'FAIL', 'IGNORE', 'REPLACE', 'ROLLBACK'], array_column(KeyConflict::cases(), 'value'));
    }
}
