<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Star;

#[CoversClass(Star::class)]
#[Medium]
final class StarTest extends TestCase
{
    public function testRenderWritesTheStar(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('select * from t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Star::class, $operation->statement->items[0]);
        self::assertSame('SELECT * FROM t', $operation->toString());
    }
}
