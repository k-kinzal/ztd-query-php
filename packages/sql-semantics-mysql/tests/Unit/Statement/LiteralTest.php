<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Statement\Scalar;

#[CoversNothing]
#[Small]
final class LiteralTest extends TestCase
{
    public function testSignedLiteralHasARestrictedUsePosition(): void
    {
        self::assertContains(Literal::class, class_implements(SignedLiteral::class));
        self::assertNotContains(Scalar::class, class_implements(SignedLiteral::class));
        self::assertContains(Scalar::class, class_implements(NumberLiteral::class));
    }
}
