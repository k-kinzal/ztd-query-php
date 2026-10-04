<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Query\FetchCounts::class)]
#[Small]
final class FetchCountsTest extends TestCase
{
    public function testAdmitsAPrimaryExpressionOrASignedConstant(): void
    {
        self::assertSame([true, false], [(new \SqlSemantics\Platform\PostgreSql\Rules\Query\FetchCounts())->admits(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'))), (new \SqlSemantics\Platform\PostgreSql\Rules\Query\FetchCounts())->admits(new \SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName(new \SqlSemantics\Statement\Identifier\Name('+')), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'))))]);
    }
}
