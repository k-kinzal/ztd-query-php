<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access;

use MySqlMemory\Command\Access\Handler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Handler::class)]
#[Small]
final class HandlerTest extends TestCase
{
    public function testPositionStartsBeforeTheFirstRow(): void
    {
        $handler = new Handler('d', 't', 'h');

        self::assertSame(['d', 't', 'h', null, false, -1], [$handler->schema, $handler->table, $handler->name, $handler->order, $handler->placed, $handler->position]);
    }
}
