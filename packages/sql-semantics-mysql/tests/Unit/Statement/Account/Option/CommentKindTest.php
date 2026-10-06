<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Option\CommentKind;

#[CoversClass(CommentKind::class)]
#[Small]
final class CommentKindTest extends TestCase
{
    public function testCasesSpellTheClauses(): void
    {
        self::assertSame(['ATTRIBUTE', 'COMMENT'], array_column(CommentKind::cases(), 'value'));
    }
}
