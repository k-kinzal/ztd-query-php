<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\TypedLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;

#[CoversClass(OutputNaming::class)]
#[Small]
final class OutputNamingTest extends TestCase
{
    public function testOutputNameIsOfferedByTheExpressionsThatNameAColumn(): void
    {
        self::assertContains(OutputNaming::class, class_implements(ColumnReference::class));
        self::assertContains(OutputNaming::class, class_implements(TypedLiteral::class));
    }
}
