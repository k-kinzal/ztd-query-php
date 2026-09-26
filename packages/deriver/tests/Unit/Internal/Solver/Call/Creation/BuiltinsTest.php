<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call\Creation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Call\Creation\Builtins
 */
#[CoversClass(\Deriver\Internal\Solver\Call\Creation\Builtins::class)]
#[Small]
final class BuiltinsTest extends TestCase
{
    public function testHierarchyPreservesTheNativeThrowableFamilies(): void
    {
        self::assertSame('LogicException', (new \Deriver\Internal\Solver\Call\Creation\Builtins())->hierarchy()['BadFunctionCallException']);
        self::assertSame('Error', (new \Deriver\Internal\Solver\Call\Creation\Builtins())->hierarchy()['TypeError']);
    }
    public function testNameCanonicalizesWithoutLoadingUnknownClasses(): void
    {
        self::assertSame('RuntimeException', (new \Deriver\Internal\Solver\Call\Creation\Builtins())->name('RUNTIMEexception'));
        self::assertNull((new \Deriver\Internal\Solver\Call\Creation\Builtins())->name('ApplicationException'));
    }
    public function testParentDistinguishesTheNativeErrorChain(): void
    {
        self::assertSame('ArithmeticError', (new \Deriver\Internal\Solver\Call\Creation\Builtins())->parent('divisionbyzeroerror'));
        self::assertSame('', (new \Deriver\Internal\Solver\Call\Creation\Builtins())->parent('stdClass'));
    }
}
