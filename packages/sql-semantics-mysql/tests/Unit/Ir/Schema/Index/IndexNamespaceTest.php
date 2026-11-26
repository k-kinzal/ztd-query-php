<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexNamespace;

#[CoversClass(IndexNamespace::class)]
#[Small]
final class IndexNamespaceTest extends TestCase
{
    public function testCheckAdmitsTheLastPositionForADistinctName(): void
    {
        $keys = [new Identifier('PRIMARY'), new Identifier('ELAN'),
            ...array_map(static fn (int $ordinal): Identifier => new Identifier('prior_' . $ordinal), range(1, 61))];
        (new IndexNamespace())->check(new Identifier('Élan'), $keys, true);

        self::assertCount(63, $keys);
    }
}
