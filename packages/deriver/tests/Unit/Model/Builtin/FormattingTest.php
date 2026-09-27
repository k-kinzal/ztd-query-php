<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Builtin;

use Deriver\Model\Builtin\Formatting;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Builtin\Formatting
 */
#[CoversClass(Formatting::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class FormattingTest extends TestCase
{
    public function testApplyPreservesPositionalArgumentsAndEscapedPercents(): void
    {
        self::assertSame('id:7/%/x', (new Formatting())->apply([Term::constant('id:%2$d/%%/%1$s'), Term::fromNative(['x', 7])])->native());
    }
    public function testApplyReportsMissingArguments(): void
    {
        $value = (new Formatting())->apply([Term::constant('%s'), Term::array([])]);
        self::assertSame('throwable', $value->kind);
        self::assertSame('ArgumentCountError', $value->literal);
    }
    public function testApplyRetainsUnsupportedFormatsAsResiduals(): void
    {
        $value = (new Formatting())->apply([Term::constant('%08d'), Term::fromNative([7])]);
        self::assertSame('opaque', $value->kind);
        self::assertSame('UNSUPPORTED_MODEL_CASE', $value->literal);
    }
}
