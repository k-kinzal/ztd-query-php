<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind::class)]
#[Small]
final class UtilityProblemKindTest extends TestCase
{
    public function testMessagesAreTheWordsOfTheServer(): void
    {
        self::assertSame('unrecognized %s option "%s"', \SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind::UnknownOption->value);
    }
}
