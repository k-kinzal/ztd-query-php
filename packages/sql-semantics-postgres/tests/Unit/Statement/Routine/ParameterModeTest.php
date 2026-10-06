<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ParameterMode;

#[CoversClass(ParameterMode::class)]
#[Small]
final class ParameterModeTest extends TestCase
{
    public function testInputExcludesOnlyOut(): void
    {
        self::assertSame([true, false, true, true, true], [ParameterMode::In->input(), ParameterMode::Out->input(), ParameterMode::InOut->input(), ParameterMode::InAndOut->input(), ParameterMode::Variadic->input()]);
    }

    public function testOutputIncludesTheOutputModes(): void
    {
        self::assertSame([false, true, true, true, false], [ParameterMode::In->output(), ParameterMode::Out->output(), ParameterMode::InOut->output(), ParameterMode::InAndOut->output(), ParameterMode::Variadic->output()]);
    }

    public function testKeywordsSpellTheMode(): void
    {
        self::assertSame(['IN', 'OUT'], ParameterMode::InAndOut->keywords());
        self::assertSame(['INOUT'], ParameterMode::InOut->keywords());
    }
}
