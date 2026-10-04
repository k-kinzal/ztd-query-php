<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperator::class)]
#[Small]
final class SetOperatorTest extends TestCase
{
    public function testLevelBindsIntersectMoreTightly(): void
    {
        self::assertSame([1, 2, 1], [\SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperator::Union->level(), \SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperator::Intersect->level(), \SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperator::Except->level()]);
    }
}
