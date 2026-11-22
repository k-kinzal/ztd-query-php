<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Dml\HandlerFacts;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(HandlerFacts::class)]
#[Medium]
final class HandlerFactsTest extends TestCase
{
    public function testReadRecordsTheRowsOfTheHandler(): void
    {
        $operation = (new Inspection())->inspect('HANDLER h READ i = (1) WHERE h.a = 2 LIMIT 1');

        self::assertNotNull($operation->facts->output);
        self::assertSame([], $operation->facts->diagnostics);
    }
}
