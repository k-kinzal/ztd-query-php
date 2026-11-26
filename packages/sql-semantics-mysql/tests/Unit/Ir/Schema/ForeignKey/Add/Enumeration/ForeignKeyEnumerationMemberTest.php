<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Enumeration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationMember;

#[CoversClass(ForeignKeyEnumerationMember::class)]
final class ForeignKeyEnumerationMemberTest extends TestCase
{
    public function testDecodedLabelPreservesAllStoredBytes(): void
    {
        $member = new ForeignKeyEnumerationMember("😀\0");

        self::assertSame('f09f988000', bin2hex($member->value));
    }
}
