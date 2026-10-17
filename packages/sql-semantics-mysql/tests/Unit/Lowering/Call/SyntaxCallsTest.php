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
    #[\PHPUnit\Framework\Attributes\TestWith(['5.6.51', 'SELECT OLD_PASSWORD((1,2))', 'function_call_conflict', 'PASSWORD'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['5.7.44', 'SELECT CONTAINS((1,2),NULL)', 'geometry_function', 'MBRCONTAINS'])]
    public function testDeprecationKeepsKeywordNoticesBeforeOperandRefusals(string $release, string $sql, string $production, string $replacement): void
    {
        $parser = new \SqlParser\MySql\MySqlParser('mysql-' . $release);
        $node = $parser->parse($sql)->find($production)[0];
        $notice = (new SyntaxCalls())->deprecation($node, \SqlSemantics\Contract\GrammarRelease::from('mysql-' . $release));
        self::assertNotNull($notice);
        self::assertStringContainsString('Please use ' . $replacement . ' instead', $notice instanceof \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated ? $notice->value : $notice->message());
        self::assertNull((new SyntaxCalls())->deprecation($node, \SqlSemantics\Contract\GrammarRelease::MySql847));
    }


    public function testCompletedDescribesOnlyTheCompletedNativeSignature(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $parser = $platform->parser($profile);
        $rule = new SyntaxCalls();

        $count = $rule->completed($parser->parse('SELECT ABS()')->find('function_call_generic')[0], $lowering);
        $named = $rule->completed($parser->parse('SELECT ABS(1 AS n)')->find('function_call_generic')[0], $lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $count);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $named);
        self::assertInstanceOf(WrongArgumentCount::class, $count->problem);
        self::assertInstanceOf(NamedArgument::class, $named->problem);
        self::assertNull($rule->completed($parser->parse('SELECT ABS(1)')->find('function_call_generic')[0], $lowering));
        self::assertNull($rule->completed($parser->parse('SELECT d.ABS()')->find('function_call_generic')[0], $lowering));
        self::assertNull($rule->completed($parser->parse('SELECT 1'), $lowering));
    }
}
