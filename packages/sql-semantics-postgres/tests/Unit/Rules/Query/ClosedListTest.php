<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Query\ClosedList::class)]
#[Small]
final class ClosedListTest extends TestCase
{
    public function testOfAcceptsItemsOfTheClasses(): void
    {
        self::assertCount(1, (new \SqlSemantics\Platform\PostgreSql\Rules\Query\ClosedList())->of([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'))], [\SqlSemantics\Statement\Scalar::class], 'm'));
    }

    public function testOfRefusesAnItemOfAnotherClass(): void
    {
        $this->expectExceptionMessage('m');
        (new \SqlSemantics\Platform\PostgreSql\Rules\Query\ClosedList())->of([new \SqlSemantics\Statement\Identifier\Name('a')], [\SqlSemantics\Statement\Scalar::class], 'm');
    }
}
