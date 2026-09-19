<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Rule;

use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\Action;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Action::class)]
#[UsesClass(Location::class)]
#[Small]
final class ActionTest extends TestCase
{
    public function testLocation(): void
    {
        $item = new Action('int', ' $$ = $1; ', 'result', new Location(5, 9));

        self::assertSame('5:9', (string) $item->location());
        self::assertSame('int', $item->tag);
        self::assertSame(' $$ = $1; ', $item->code);
        self::assertSame('result', $item->namedReference);
    }
}
