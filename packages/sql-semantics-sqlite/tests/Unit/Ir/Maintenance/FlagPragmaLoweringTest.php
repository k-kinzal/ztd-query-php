<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagArgument;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagChange;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagName;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagPragmaLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\MaintenanceLowering;
use SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaNumber;

#[CoversClass(FlagArgument::class)]
#[CoversClass(FlagPragmaLowering::class)]
#[CoversClass(MaintenanceLowering::class)]
#[CoversClass(PragmaNumber::class)]
#[Small]
final class FlagPragmaLoweringTest extends TestCase
{
    public function testLowerResolvesTheNativeSchemaAndReloadAction(): void
    {
        $value = (new Semantics(searchPath: new SearchPath('main', 'archive')))->analyze("PRAGMA ARCHIVE.WRITABLE_SCHEMA('reSET')");

        self::assertInstanceOf(FlagPragma::class, $value);
        self::assertSame(FlagName::WritableSchema, $value->name);
        self::assertSame('archive', $value->schema?->value);
        self::assertSame(FlagChange::Reset, $value->change);
    }

    #[DataProvider('providerNativeBooleanArguments')]
    public function testLowerInterpretsPragmaValuesAsNativeText(string $argument, FlagChange $change): void
    {
        $value = (new Semantics())->analyze('PRAGMA automatic_index = ' . $argument);

        self::assertInstanceOf(FlagPragma::class, $value);
        self::assertSame($change, $value->change);
    }

    /**
     * @return iterable<string, array{string, FlagChange}>
     */
    public static function providerNativeBooleanArguments(): iterable
    {
        yield 'positive token' => ['+1', FlagChange::Enable];
        yield 'quoted sign' => ["'+1'", FlagChange::Disable];
        yield 'negative token' => ['-1', FlagChange::Disable];
        yield 'low byte' => ['256', FlagChange::Disable];
        yield 'nonzero high byte' => ['257', FlagChange::Enable];
        yield 'signed 32-bit overflow' => ['2147483648', FlagChange::Disable];
        yield 'over-wide hex is a pragma operand' => ['0x80000000000000000', FlagChange::Disable];
        yield 'hex prefix' => ["'0X101tail'", FlagChange::Enable];
        yield 'decimal exponent' => ['1.e-50', FlagChange::Enable];
        yield 'without whole part' => ['.5', FlagChange::Disable];
        yield 'boolean text' => ["'yEs'", FlagChange::Enable];
        yield 'reset on a different flag means false' => ['RESET', FlagChange::Disable];
    }
}
