<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\Symbol;

#[CoversClass(Production::class)]
#[UsesClass(Symbol::class)]
final class ProductionTest extends TestCase
{
    public function testReportsReferencesAndSequence(): void
    {
        $production = new Production([
            new Symbol('SELECT', false),
            new Symbol('expr', true),
            new Symbol(',', false),
            new Symbol('table_ref', true),
        ]);

        self::assertSame(['expr', 'table_ref'], $production->references());
        self::assertSame(['t:SELECT', 'nt:expr', 't:,', 'nt:table_ref'], $production->sequence());
    }

    public function testRejectsNonListSymbols(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Production symbols must be a list.');

        $symbols = ['expr' => new Symbol('expr', true)];

        new Production($symbols);
    }

    public function testRejectsNonSymbolValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Production symbols must contain only Symbol values.');

        $symbols = [new \stdClass()];

        new Production($symbols);
    }
}
