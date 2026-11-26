<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\RenameColumn;

#[CoversClass(RenameColumn::class)]
#[Small]
final class RenameColumnTest extends TestCase
{
    public function testToStringDistinguishesTheColumnSourceAndTheEmptyDestination(): void
    {
        $value = new RenameColumn(new Identifier('main'), new Identifier('records'), new Identifier('old'), new Identifier(''));

        self::assertSame('ALTER TABLE "main"."records" RENAME COLUMN "old" TO ""', $value->toString());
    }

    public function testToStringKeepsABareDestinationWhenRequested(): void
    {
        $value = new RenameColumn(new Identifier('temp'), new Identifier('records'), new Identifier('old'), new Identifier('kept'), false);

        self::assertSame('ALTER TABLE "temp"."records" RENAME COLUMN "old" TO kept', $value->toString());
    }

    public function testProfileIdNamesTheNativeRelease(): void
    {
        $value = new RenameColumn(new Identifier('main'), new Identifier('records'), new Identifier('old'), new Identifier('NEW'));

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', $value->profileId());
    }
}
