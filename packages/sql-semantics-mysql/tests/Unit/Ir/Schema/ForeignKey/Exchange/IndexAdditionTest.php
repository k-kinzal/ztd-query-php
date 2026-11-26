<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Exchange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\IndexAddition;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(IndexAddition::class)]
#[Small]
final class IndexAdditionTest extends TestCase
{
    public function testToStringPreservesTheAllocationChoiceAndTheKeyRole(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $key = NativeAdditionFixture::key($owner, 'code_2', IndexRole::Unique, 'code', true);
        $heading = new TableSource($owner->name->schema, $owner->name->table, [new Identifier('code')]);

        self::assertSame('ADD UNIQUE INDEX (`code` DESC)', (new IndexAddition($key, $heading, true))->toString());
        self::assertSame('ADD UNIQUE INDEX `code_2` (`code` DESC)', (new IndexAddition($key, $heading))->toString());
    }
}
