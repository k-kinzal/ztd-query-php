<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedLanguage;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RefusedLanguage::class)]
#[Small]
final class RefusedLanguageTest extends TestCase
{
    public function testMessageNamesTheLanguage(): void
    {
        $problem = new RefusedLanguage(new Name('python'), 30);

        self::assertSame("Unknown routine language 'python'", $problem->message());
        self::assertSame(30, $problem->offset);
    }
}
