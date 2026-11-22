<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\NotNullConflict;

#[CoversClass(NotNullConflict::class)]
#[Small]
final class NotNullConflictTest extends TestCase
{
    public function testValueDomainIsTheFiveConstraintAlgorithms(): void
    {
        self::assertSame(['ABORT', 'FAIL', 'IGNORE', 'REPLACE', 'ROLLBACK'], array_column(NotNullConflict::cases(), 'value'));
    }
}
