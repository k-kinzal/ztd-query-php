<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(SearchPath::class)]
#[Medium]
final class SearchPathTest extends TestCase
{
    public function testSchemasKeepThePrecedenceOrder(): void
    {
        $path = new SearchPath('main', 'aux');

        self::assertSame(['main', 'aux'], $path->schemas);
    }

    public function testSchemasKeepAnEmptyAttachedName(): void
    {
        $path = new SearchPath('main', '');

        self::assertSame(['main', ''], $path->schemas);
    }

    public function testSchemasRefuseAnEmptyPath(): void
    {
        $this->expectExceptionMessage('A search path names at least one schema.');

        new SearchPath();
    }

    public function testSchemasBecomeTheSearchedSchemasOfTheContext(): void
    {
        $semantics = new Inspection(null, null, searchPath: new SearchPath('main', 'aux'));

        self::assertSame(['temp', 'main', 'aux'], array_map(static fn (Name $name): string => $name->value, $semantics->context()->searchPath));
    }
}
