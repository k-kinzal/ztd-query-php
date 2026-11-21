<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName
 */
final class DeclaredDatatypeNameTest extends TestCase
{
    public function testBlobAndUntypedHaveDistinctCatalogNames(): void
    {
        self::assertSame('BLOB', DeclaredDatatypeName::Blob->value);
        self::assertSame('', DeclaredDatatypeName::Untyped->value);
    }
}
