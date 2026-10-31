<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseReadOnly;

#[CoversClass(DatabaseReadOnly::class)]
#[Medium]
final class DatabaseReadOnlyTest extends TestCase
{
    public function testRenderWritesTheValue(): void
    {
        self::assertSame('ALTER DATABASE d READ ONLY 0', (new Semantics(Dialect::MySql))->analyze('alter database d read only 0')->toString());
    }

    public function testReadOnlyTellsWhetherTheNumberIsNotZero(): void
    {
        self::assertTrue((new DatabaseReadOnly(new Numeral('01.9')))->readOnly());
        self::assertFalse((new DatabaseReadOnly(new Numeral('0.9')))->readOnly());
        self::assertFalse((new DatabaseReadOnly(null))->readOnly());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerInvalidValues(): iterable
    {
        yield 'two' => ['2', false];
        yield 'fraction' => ['2.0', false];
        yield 'exponent' => ['2e-10', false];
        yield 'unsigned boundary' => ['18446744073709551616', false];
        yield 'hexadecimal' => ['02', true];
        yield 'hexadecimal overflow' => ['FFFFFFFFFFFFFFFFFF', true];
    }

    #[DataProvider('providerInvalidValues')]
    public function testValueRefusesNumbersOutsideTheBooleanDomain(string $text, bool $hexadecimal): void
    {
        $this->expectExceptionMessage('READ ONLY option converts to 0 or 1');

        new DatabaseReadOnly(new Numeral($text, $hexadecimal));
    }
}
