<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord;

#[CoversClass(RoleWord::class)]
#[Small]
final class RoleWordTest extends TestCase
{
    public function testCasesSpellTheKeyword(): void
    {
        self::assertSame(['ROLE', 'USER', 'GROUP'], [RoleWord::Role->value, RoleWord::User->value, RoleWord::Group->value]);
    }
}
