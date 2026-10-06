<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsAttribute;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RepeatedTlsAttribute;

#[CoversClass(RepeatedTlsAttribute::class)]
#[Small]
final class RepeatedTlsAttributeTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('SUBJECT is required more than once (ER_DUP_ARGUMENT).', (new RepeatedTlsAttribute(TlsAttribute::Subject))->message());
    }
}
