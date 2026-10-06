<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Query\TargetNames::class)]
#[Small]
final class TargetNamesTest extends TestCase
{
    public function testNameFallsBackToQuestionColumn(): void
    {
        self::assertSame('?column?', (new \SqlSemantics\Platform\PostgreSql\Rules\Query\TargetNames())->name(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')))->value);
    }

    public function testFirstIsNullForALeadingStar(): void
    {
        self::assertNull((new \SqlSemantics\Platform\PostgreSql\Rules\Query\TargetNames())->first([new \SqlSemantics\Platform\PostgreSql\Statement\Query\StarTarget()]));
    }
}
