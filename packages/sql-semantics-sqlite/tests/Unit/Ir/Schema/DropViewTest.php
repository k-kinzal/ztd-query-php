<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DropView;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\DropView
 */
final class DropViewTest extends TestCase
{
    public function testToStringQuotesTheResolvedSchemaAndViewSeparately(): void
    {
        $value = new DropView(new Identifier('main'), new Identifier('both"parts'));

        self::assertSame('DROP VIEW "main"."both""parts"', $value->toString());
    }

    public function testProfileIdUsesTheFixedSqliteProfile(): void
    {
        $value = new DropView(new Identifier('temp'), new Identifier('scratch'), true);

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', $value->profileId());
    }
}
