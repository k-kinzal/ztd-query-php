<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableOptionKind;

#[CoversClass(TableOptionKind::class)]
#[Small]
final class TableOptionKindTest extends TestCase
{
    public function testCasesNameTheOptionsSqliteKnows(): void
    {
        self::assertSame(['WithoutRowid', 'Strict'], array_column(TableOptionKind::cases(), 'name'));
    }
}
