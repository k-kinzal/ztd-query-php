<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call\Creation;

use Deriver\Evaluation\Call\Creation\Builtins;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Call\Creation\Builtins
 */
#[CoversClass(Builtins::class)]
#[Small]
final class BuiltinsTest extends TestCase
{
    public function testHierarchyPreservesTheNativeThrowableFamilies(): void
    {
        self::assertSame('LogicException', (new Builtins())->hierarchy()['BadFunctionCallException']);
        self::assertSame('Error', (new Builtins())->hierarchy()['TypeError']);
    }
    public function testNameCanonicalizesWithoutLoadingUnknownClasses(): void
    {
        self::assertSame('RuntimeException', (new Builtins())->name('RUNTIMEexception'));
        self::assertNull((new Builtins())->name('ApplicationException'));
    }
    public function testParentDistinguishesTheNativeErrorChain(): void
    {
        self::assertSame('ArithmeticError', (new Builtins())->parent('divisionbyzeroerror'));
        self::assertSame('', (new Builtins())->parent('stdClass'));
    }
}
