<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UnknownExplainFormat;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(UnknownExplainFormat::class)]
#[Small]
final class UnknownExplainFormatTest extends TestCase
{
    public function testMessageRetainsTheDecodedFormat(): void
    {
        $problem = new UnknownExplainFormat(new Name('YaML'));

        self::assertSame(['Unknown EXPLAIN format name', 'YaML'], [$problem->message(), $problem->name->value]);
    }
}
