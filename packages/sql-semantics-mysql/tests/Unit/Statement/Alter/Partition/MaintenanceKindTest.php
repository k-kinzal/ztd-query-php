<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintenanceKind;

#[CoversClass(MaintenanceKind::class)]
#[Small]
final class MaintenanceKindTest extends TestCase
{
    public function testLoggedAnswersTheOperationsThatTakeNoWriteToBinlog(): void
    {
        self::assertSame(['REBUILD', 'OPTIMIZE', 'ANALYZE', 'REPAIR'], array_column(array_values(array_filter(MaintenanceKind::cases(), static fn (MaintenanceKind $kind): bool => $kind->logged())), 'value'));
    }
}
