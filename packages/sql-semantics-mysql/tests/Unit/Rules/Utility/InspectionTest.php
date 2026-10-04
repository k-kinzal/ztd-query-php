<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Rules\Utility\Inspection;
use SqlSemantics\Rendering\Output;

#[CoversClass(Inspection::class)]
#[Medium]
final class InspectionTest extends TestCase
{
    public function testDeriveStatementInspectsTheWrappedStatement(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE db.t (a INT)');
        $explain = $semantics->analyze('EXPLAIN FORMAT = JSON FOR DATABASE db SELECT a FROM t', [$table]);
        self::assertSame([], $explain->facts->diagnostics);
        self::assertSame('EXPLAIN', $explain->field(0)->name?->value);
    }

    public function testRenderRefusesToWrite(): void
    {
        $this->expectExceptionMessage('An inspection is a derivation step and has no SQL.');
        (new Inspection((new Semantics(Dialect::MySql))->analyze('SELECT 1')->statement))->render(new Output(new Codec(GrammarRelease::MySql847)));
    }
}
