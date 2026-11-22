<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Option\UserComment;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(UserComment::class)]
#[Medium]
final class UserCommentTest extends TestCase
{
    public function testRenderWritesTheClause(): void
    {
        self::assertSame('ALTER USER u ATTRIBUTE \'{"team": "ops"}\'', (new Inspection())->inspect('alter user u attribute \'{"team": "ops"}\'')->toString());
    }
}
