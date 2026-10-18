<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Micro;

use Deriver\Deriver\Evaluator\Micro\Site;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Sources\SourceRef;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SiteTest extends TestCase
{
    public function testInputsRemainLazyUntilRequested(): void
    {
        $opened = 0;
        $input = static function (int|string $key) use (&$opened): Term {
            ++$opened;
            return Term::constant($key);
        };
        $source = new SourceRef('s', 'a.php', 4, 9);
        $site = new Site('branch', '', $source, $input, static fn (): Term => Term::constant(true));
        self::assertSame(0, $opened);
        self::assertSame($source, $site->source);
        self::assertSame(4, ($site->input)(4)->native());
        self::assertSame(true, ($site->truth)(0)->native());
        self::assertSame(1, $opened);
    }
}
