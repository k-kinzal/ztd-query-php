<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;

#[CoversClass(ColumnKeyword::class)]
#[Small]
final class ColumnKeywordTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame('AUTO_INCREMENT', ColumnKeyword::AutoIncrement->value);
        self::assertSame('SERIAL DEFAULT VALUE', ColumnKeyword::SerialDefaultValue->value);
    }
}
