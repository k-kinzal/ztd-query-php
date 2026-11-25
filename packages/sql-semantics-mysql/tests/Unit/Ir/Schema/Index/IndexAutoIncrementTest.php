<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexAutoIncrement;

#[CoversClass(IndexAutoIncrement::class)]
#[Small]
final class IndexAutoIncrementTest extends TestCase
{
    public function testTheAlternateListDetachesReferences(): void
    {
        $key = new Identifier('retained');
        $support = new IndexAutoIncrement(new Identifier('id'), [&$key]);
        $key = new Identifier('changed');

        self::assertSame('retained', $support->alternates[0]->value);
        self::assertSame('changed', $key->value);
    }
}
