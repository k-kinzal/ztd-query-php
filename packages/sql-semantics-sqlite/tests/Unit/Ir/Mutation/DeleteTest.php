<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(Delete::class)]
#[Small]
final class DeleteTest extends TestCase
{
    public function testProfileIdIdentifiesThePinnedDialect(): void
    {
        $target = new TableSource(new Identifier('main'), new Identifier('entries'), []);

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new Delete($target))->profileId());
    }

    public function testToStringQualifiesTheBoundTargetAndColumn(): void
    {
        $target = new TableSource(new Identifier('temp'), new Identifier('events'), [new Identifier('active')]);

        self::assertSame(
            'DELETE FROM "temp"."events" AS "__ztd_source" WHERE "__ztd_source"."active"',
            (new Delete($target, new ColumnReference(0)))->toString()
        );
    }
}
