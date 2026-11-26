<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Facade\Catalog;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(Catalog::class)]
#[Small]
final class CatalogTest extends TestCase
{
    public function testBuildBindsSqlDefinitions(): void
    {
        $platform = Platform::shared();
        $profile = (new Semantics())->profile();
        $catalog = new Catalog($profile, null, $platform->parser($profile));
        $context = $catalog->build(['CREATE TABLE app.accounts (id INTEGER)'], true);

        self::assertTrue($context->complete);
        self::assertCount(1, $context->tables);
        self::assertSame('accounts', $context->tables[0]->name->name->value);
    }

    public function testBuildRejectsSqlThatDeclaresNoRelation(): void
    {
        $platform = Platform::shared();
        $profile = (new Semantics())->profile();
        $catalog = new Catalog($profile, null, $platform->parser($profile));
        $this->expectExceptionMessage('A catalog definition must declare a relation.');

        $catalog->build(['SELECT 1'], true);
    }

    public function testResolveChecksThePreparedContext(): void
    {
        $platform = Platform::shared();
        $profile = (new Semantics())->profile();
        $catalog = new Catalog($profile, null, $platform->parser($profile));
        $context = $catalog->build(null, false);

        self::assertFalse($context->complete);
        self::assertSame($context, $catalog->resolve($context));
    }
}
