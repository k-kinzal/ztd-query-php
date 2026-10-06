<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;

#[CoversClass(NotSupportedYet::class)]
#[Small]
final class NotSupportedYetTest extends TestCase
{
    public function testMessageNamesTheForm(): void
    {
        self::assertSame("This version of MySQL doesn't yet support 'AT LOCAL'.", (new NotSupportedYet('AT LOCAL'))->message());
    }

    public function testAnUnnamedFormIsRejected(): void
    {
        $this->expectExceptionMessage('An unsupported form is named.');

        new NotSupportedYet('');
    }
}
