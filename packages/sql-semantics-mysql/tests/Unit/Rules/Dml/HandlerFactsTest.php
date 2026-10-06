<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Dml\HandlerFacts;

#[CoversClass(HandlerFacts::class)]
#[Medium]
final class HandlerFactsTest extends TestCase
{
    public function testReadRecordsTheRowsOfTheHandler(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('HANDLER h READ i = (1) WHERE h.a = 2 LIMIT 1');

        self::assertNotNull($operation->facts->output);
        self::assertSame([], $operation->facts->diagnostics);
    }
}
