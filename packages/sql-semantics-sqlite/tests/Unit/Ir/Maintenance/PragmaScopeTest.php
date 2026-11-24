<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\PragmaScope;
use SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(PragmaScope::class)]
#[Small]
final class PragmaScopeTest extends TestCase
{
    public function testResolveFindsCanonicalAttachedNameAndLeavesImplicitAccessUnqualified(): void
    {
        $semantics = new Semantics(searchPath: new SearchPath('main', 'Archive'));
        $specified = $semantics->analyze('PRAGMA ARCHIVE.function_list(+1)');
        $implicit = $semantics->analyze('PRAGMA function_list');

        self::assertInstanceOf(InventoryPragma::class, $specified);
        self::assertInstanceOf(InventoryPragma::class, $implicit);
        self::assertSame('Archive', $specified->schema?->value);
        self::assertSame('PRAGMA "Archive".function_list', $specified->toString());
        self::assertNull($implicit->schema);
    }
}
