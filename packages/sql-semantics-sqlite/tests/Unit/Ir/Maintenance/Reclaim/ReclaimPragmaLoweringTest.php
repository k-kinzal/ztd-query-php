<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Reclaim;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\AutoVacuum;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\IncrementalVacuum;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\ReclaimPragmaLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\VacuumMode;
use SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(ReclaimPragmaLowering::class)]
#[Small]
final class ReclaimPragmaLoweringTest extends TestCase
{
    public function testAutomaticResolvesModeNamesAndPreservesInvalidArgumentsAsWrites(): void
    {
        $semantics = new Semantics(searchPath: new SearchPath('main', 'archive'));
        $context = $semantics->context([]);
        $change = $semantics->analyze("PRAGMA ArChIvE.auto_vacuum='InCreMenTal'", $context);
        $fallback = $semantics->analyze('PRAGMA auto_vacuum(3)');
        $query = $semantics->analyze('PRAGMA auto_vacuum');

        self::assertInstanceOf(AutoVacuum::class, $change);
        self::assertSame('archive', $change->schema->value);
        self::assertSame(VacuumMode::Incremental, $change->mode);
        self::assertInstanceOf(AutoVacuum::class, $fallback);
        self::assertSame(VacuumMode::None, $fallback->mode);
        self::assertInstanceOf(AutoVacuum::class, $query);
        self::assertNull($query->mode);
    }

    public function testIncrementalUsesTheDecodedPositivePrefixOrTheNativeMaximum(): void
    {
        $semantics = new Semantics();
        $finite = $semantics->analyze("PRAGMA temp.incremental_vacuum('2tail')");
        $negative = $semantics->analyze('PRAGMA incremental_vacuum(-2)');

        self::assertInstanceOf(IncrementalVacuum::class, $finite);
        self::assertSame('temp', $finite->schema->value);
        self::assertSame(2, $finite->limit->pages);
        self::assertInstanceOf(IncrementalVacuum::class, $negative);
        self::assertSame(2147483647, $negative->limit->pages);
    }
}
