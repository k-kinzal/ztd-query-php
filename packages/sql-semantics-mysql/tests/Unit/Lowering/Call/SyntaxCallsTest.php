<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Call\SyntaxCalls;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount;

#[CoversClass(SyntaxCalls::class)]
#[Small]
final class SyntaxCallsTest extends TestCase
{
    public function testCompletedDescribesOnlyTheCompletedNativeSignature(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $parser = $platform->parser($profile);
        $rule = new SyntaxCalls();

        self::assertInstanceOf(WrongArgumentCount::class, $rule->completed($parser->parse('SELECT ABS()')->find('function_call_generic')[0], $lowering)?->problem);
        self::assertInstanceOf(NamedArgument::class, $rule->completed($parser->parse('SELECT ABS(1 AS n)')->find('function_call_generic')[0], $lowering)?->problem);
        self::assertNull($rule->completed($parser->parse('SELECT ABS(1)')->find('function_call_generic')[0], $lowering));
        self::assertNull($rule->completed($parser->parse('SELECT d.ABS()')->find('function_call_generic')[0], $lowering));
        self::assertNull($rule->completed($parser->parse('SELECT 1'), $lowering));
    }
}
