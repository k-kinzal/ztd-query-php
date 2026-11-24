<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Reclaim;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\AutoVacuum;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\VacuumMode;

#[CoversClass(AutoVacuum::class)]
#[Small]
final class AutoVacuumTest extends TestCase
{
    public function testToStringDistinguishesAReadFromDisablingReclamation(): void
    {
        $disabled = new AutoVacuum(new Identifier('arc"hive'), VacuumMode::None);

        self::assertSame('PRAGMA "main".auto_vacuum', (new AutoVacuum())->toString());
        self::assertSame('PRAGMA "arc""hive".auto_vacuum(0)', $disabled->toString());
    }

    public function testProfileIdIdentifiesThePinnedLanguage(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new AutoVacuum())->profileId());
    }
}
