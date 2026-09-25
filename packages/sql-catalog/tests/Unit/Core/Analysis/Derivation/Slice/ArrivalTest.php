<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis\Derivation\Slice;

use PhpParser\Node\Stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\Derivation\Slice\Arrival;
use SqlCatalog\Core\Analysis\Derivation\Slice\Pending;

#[CoversClass(Arrival::class)]
#[UsesClass(Pending::class)]
final class ArrivalTest extends TestCase
{
    public function testAnArrivalNamesTheBodyAndThePathThatReachedItsStart(): void
    {
        $body = new Stmt\Function_('f');
        $path = Pending::needing(['id' => true]);

        $arrival = new Arrival($body, $path);

        self::assertSame($body, $arrival->body);
        self::assertSame($path, $arrival->path);
    }

    public function testAnArrivalAtTheTopOfAFileHasNoBody(): void
    {
        self::assertNull((new Arrival(null, Pending::needing([])))->body);
    }
}
