<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\MissingIndex;

#[CoversClass(MissingIndex::class)]
#[Small]
final class MissingIndexTest extends TestCase
{
    public function testQuotedDistinguishesAnEmptyQualifierFromNoQualifier(): void
    {
        $index = new Identifier('field "x"');

        self::assertSame('""."field ""x"""', (new MissingIndex($index, new Identifier('')))->quoted());
        self::assertSame('"field ""x"""', (new MissingIndex($index))->quoted());
    }
}
