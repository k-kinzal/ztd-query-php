<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Option\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind;

#[CoversClass(TextOptionKind::class)]
#[Small]
final class TextOptionKindTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame('INDEX DIRECTORY', TextOptionKind::IndexDirectory->value);
    }
}
