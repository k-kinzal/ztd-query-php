<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Exchange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\ExplicitKeyChecks;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\IndexAddition;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ExplicitKeyChecks::class)]
#[Small]
final class ExplicitKeyChecksTest extends TestCase
{
    public function testRequestsRetainsExplicitAndAutomaticActionPositions(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $first = NativeAdditionFixture::key($owner, 'Native', IndexRole::Ordinary, 'code');
        $second = NativeAdditionFixture::key($owner, 'code', IndexRole::Unique, 'code', true);
        $heading = new TableSource($owner->name->schema, $owner->name->table, [new Identifier('code')]);
        $primary = NativeAdditionFixture::key($owner, 'PRIMARY', IndexRole::Primary, 'id');
        $edits = [3 => new IndexAddition($first, $heading), 6 => new IndexAddition($second, $heading, true)];

        self::assertSame(
            [3 => [$first->identity->name, $first->fields], 6 => [null, $second->fields]],
            (new ExplicitKeyChecks())->requests($owner, [$primary], $edits, false)
        );
    }
}
