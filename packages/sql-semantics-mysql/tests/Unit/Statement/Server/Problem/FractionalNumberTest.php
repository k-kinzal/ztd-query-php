<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber;

#[CoversClass(FractionalNumber::class)]
#[Small]
final class FractionalNumberTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('Only integers are allowed, not 2e1 (ER_ONLY_INTEGERS_ALLOWED).', (new FractionalNumber(new Numeral('2e1')))->message());
    }
}
