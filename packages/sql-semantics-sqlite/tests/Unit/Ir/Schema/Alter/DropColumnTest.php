<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\DropColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\DropMember;

#[CoversClass(DropColumn::class)]
#[Small]
final class DropColumnTest extends TestCase
{
    public function testToStringUsesTheSelectedColumnAndAbsoluteTable(): void
    {
        $member = new DropMember(new Identifier('old"slot'), generated: true);
        $heading = [new DropMember(new Identifier('id'), protected: true), &$member];
        $drop = new DropColumn(new Identifier('temp'), new Identifier('source'), $heading, 1);
        $member = new DropMember(new Identifier('different'));

        self::assertSame(1, $drop->slot);
        self::assertSame('old"slot', $drop->column->value);
        self::assertTrue($drop->generated);
        self::assertSame('ALTER TABLE "temp"."source" DROP COLUMN "old""slot"', $drop->toString());
    }

    public function testProfileIdRecordsTheFixedProfile(): void
    {
        $drop = new DropColumn(new Identifier('main'), new Identifier('source'), [
            new DropMember(new Identifier('id')),
            new DropMember(new Identifier('computed'), generated: true),
        ], 1);

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', $drop->profileId());
    }
}
