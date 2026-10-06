<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(SearchPath::class)]
#[Medium]
final class SearchPathTest extends TestCase
{
    public function testSchemasKeepThePrecedenceOrder(): void
    {
        $path = new SearchPath('main', 'aux');

        self::assertSame(['main', 'aux'], $path->schemas);
    }

    public function testSchemasRefuseAnEmptyName(): void
    {
        $this->expectExceptionMessage('A search path names at least one schema and no empty name.');

        new SearchPath('main', '');
    }

    public function testSchemasRefuseAnEmptyPath(): void
    {
        $this->expectExceptionMessage('A search path names at least one schema and no empty name.');

        new SearchPath();
    }

    public function testSchemasBecomeTheSearchedSchemasOfTheContext(): void
    {
        $semantics = new Semantics(Dialect::Sqlite, null, null, searchPath: new SearchPath('main', 'aux'));

        self::assertSame(['temp', 'main', 'aux'], array_map(static fn (Name $name): string => $name->value, $semantics->context()->searchPath));
    }
}
