<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Option\UserComment;

#[CoversClass(UserComment::class)]
#[Medium]
final class UserCommentTest extends TestCase
{
    public function testRenderWritesTheClause(): void
    {
        self::assertSame('ALTER USER u ATTRIBUTE \'{"team": "ops"}\'', (new Semantics(Dialect::MySql))->analyze('alter user u attribute \'{"team": "ops"}\'')->toString());
    }
}
