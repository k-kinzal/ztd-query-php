<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;

#[CoversClass(UnknownSystemVariable::class)]
#[Small]
final class UnknownSystemVariableTest extends TestCase
{
    public function testMessageNamesTheVariable(): void
    {
        self::assertSame("Unknown system variable 'nosuch'", (new UnknownSystemVariable('nosuch'))->message());
    }
}
