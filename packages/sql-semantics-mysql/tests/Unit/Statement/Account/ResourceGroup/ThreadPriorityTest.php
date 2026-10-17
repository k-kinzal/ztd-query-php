<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

#[CoversClass(ThreadPriority::class)]
#[Medium]
final class ThreadPriorityTest extends TestCase
{
    public function testDescribeWritesTheSign(): void
    {
        self::assertSame('7', (new ThreadPriority(false, new Numeral('7')))->describe());
    }

    public function testRenderWritesTheSign(): void
    {
        self::assertSame('ALTER RESOURCE GROUP g THREAD_PRIORITY = -5', (new Semantics(Dialect::MySql))->analyze('alter resource group g thread_priority = -5')->toString());
    }

    public function testPriorityRejectsAFractionalToken(): void
    {
        $this->expectExceptionMessage('A thread priority uses a decimal integer token no greater than 2147483647.');
        new ThreadPriority(false, new Numeral('1.5'));
    }

    /**
     * @return iterable<string, array{bool, string, int, bool, bool}>
     */
    public static function providerValues(): iterable
    {
        yield 'negative zero' => [true, '000', 0, true, true];
        yield 'zero' => [false, '0', 0, true, true];
        yield 'system lower bound' => [true, '020', -20, true, false];
        yield 'below system bound' => [true, '21', -21, false, false];
        yield 'above system bound' => [false, '1', 1, false, true];
        yield 'below user bound' => [true, '1', -1, true, false];
        yield 'user upper bound' => [false, '000019', 19, false, true];
        yield 'above user bound' => [false, '20', 20, false, false];
        yield 'token maximum' => [false, '2147483647', 2147483647, false, false];
    }

    #[DataProvider('providerValues')]
    public function testValueInterpretsTheSignedInteger(bool $negative, string $number, int $expected, bool $system, bool $user): void
    {
        self::assertSame($expected, (new ThreadPriority($negative, new Numeral($number)))->value());
    }

    #[DataProvider('providerValues')]
    public function testFitsUsesTheDeclaredKind(bool $negative, string $number, int $expected, bool $system, bool $user): void
    {
        $priority = new ThreadPriority($negative, new Numeral($number));

        self::assertSame([$system, $user], [$priority->fits(ResourceGroupKind::System), $priority->fits(ResourceGroupKind::User)]);
    }

}
