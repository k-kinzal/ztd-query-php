<?php

declare(strict_types=1);

namespace Tests\Unit\Standard;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Standard\Formatting
 */
#[CoversClass(\Deriver\Standard\Formatting::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class FormattingTest extends TestCase
{
    public function testApplyPreservesPositionalArgumentsAndEscapedPercents(): void
    {
        self::assertSame('id:7/%/x', (new \Deriver\Standard\Formatting())->apply([\Deriver\Value\Term::constant('id:%2$d/%%/%1$s'), \Deriver\Value\Term::fromNative(['x', 7])])->native());
    }
    public function testApplyReportsMissingArguments(): void
    {
        $value = (new \Deriver\Standard\Formatting())->apply([\Deriver\Value\Term::constant('%s'), \Deriver\Value\Term::array([])]);
        self::assertSame('throwable', $value->kind);
        self::assertSame('ArgumentCountError', $value->literal);
    }
    public function testApplyRetainsUnsupportedFormatsAsResiduals(): void
    {
        $value = (new \Deriver\Standard\Formatting())->apply([\Deriver\Value\Term::constant('%08d'), \Deriver\Value\Term::fromNative([7])]);
        self::assertSame('opaque', $value->kind);
        self::assertSame('UNSUPPORTED_MODEL_CASE', $value->literal);
    }
}
