<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Star::class)]
#[Medium]
final class StarTest extends TestCase
{
    public function testRenderWritesTheStar(): void
    {
        $operation = (new Inspection())->inspect('select * from t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Star::class, $operation->statement->items[0]);
        self::assertSame('SELECT * FROM t', $operation->toString());
    }
}
