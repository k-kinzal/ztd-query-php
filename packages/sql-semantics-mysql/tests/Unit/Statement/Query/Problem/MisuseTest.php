<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;

#[CoversClass(Misuse::class)]
#[Medium]
final class MisuseTest extends TestCase
{
    public function testMessageAnswersTheWordsOfTheServer(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT * FROM DUAL');

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertSame('No tables used', $operation->facts->diagnostics[0]->message());
    }
}
