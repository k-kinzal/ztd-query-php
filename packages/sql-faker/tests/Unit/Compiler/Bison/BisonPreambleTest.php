<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Bison;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Bison\Ast\BisonStartDeclaration;
use SqlFaker\Compiler\Bison\BisonPreamble;

#[CoversClass(BisonPreamble::class)]
#[UsesClass(BisonStartDeclaration::class)]
final class BisonPreambleTest extends TestCase
{
    public function testItKeepsThePrologueAndTheDeclarationsApart(): void
    {
        $declaration = new BisonStartDeclaration('statement');

        $preamble = new BisonPreamble('%{ int x; %}', [$declaration]);

        self::assertSame('%{ int x; %}', $preamble->prologue);
        self::assertSame([$declaration], $preamble->declarations);
    }

    public function testItReportsAnAbsentPrologueAsNull(): void
    {
        $preamble = new BisonPreamble(null, []);

        self::assertNull($preamble->prologue);
        self::assertSame([], $preamble->declarations);
    }
}
