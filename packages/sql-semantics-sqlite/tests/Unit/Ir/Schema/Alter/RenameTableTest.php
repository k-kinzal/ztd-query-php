<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\RenameTable;

#[CoversClass(RenameTable::class)]
#[Small]
final class RenameTableTest extends TestCase
{
    public function testToStringKeepsTheAbsoluteSourceAndEscapesTheUnqualifiedDestination(): void
    {
        $value = new RenameTable(new Identifier('temp'), new Identifier('old'), new Identifier('new"name'));

        self::assertSame('ALTER TABLE "temp"."old" RENAME TO "new""name"', $value->toString());
    }

    public function testProfileIdNamesTheNativeRelease(): void
    {
        $value = new RenameTable(new Identifier('main'), new Identifier('old'), new Identifier('new'));

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', $value->profileId());
    }
}
