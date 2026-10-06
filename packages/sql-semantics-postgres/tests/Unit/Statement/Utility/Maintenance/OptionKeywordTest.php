<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionKeyword;

#[CoversClass(OptionKeyword::class)]
#[Small]
final class OptionKeywordTest extends TestCase
{
    public function testOptionAnswersTheOptionNameOfEachKeyword(): void
    {
        self::assertSame(['analyze', 'analyze', 'format'], [OptionKeyword::Analyze->option(), OptionKeyword::Analyse->option(), OptionKeyword::Format->option()]);
    }
}
