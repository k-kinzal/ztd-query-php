<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextOperator;

#[CoversClass(TextOperator::class)]
#[Small]
final class TextOperatorTest extends TestCase
{
    public function testBuiltinOperatorNames(): void
    {
        self::assertSame(['=', '<>', '<', '<=', '>', '>='], array_column(TextOperator::cases(), 'value'));
    }
}
