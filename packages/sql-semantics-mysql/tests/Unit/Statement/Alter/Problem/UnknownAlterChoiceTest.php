<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownAlterChoice;

#[CoversClass(UnknownAlterChoice::class)]
#[Small]
final class UnknownAlterChoiceTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('FAST is not an ALGORITHM the server knows.', (new UnknownAlterChoice(false, new Name('FAST')))->message());
    }
}
