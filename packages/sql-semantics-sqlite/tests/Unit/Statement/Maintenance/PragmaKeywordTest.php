<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaKeyword;

#[CoversClass(PragmaKeyword::class)]
#[Small]
final class PragmaKeywordTest extends TestCase
{
    public function testCasesSpellTheKeywordsTheGrammarAcceptsAsValues(): void
    {
        self::assertSame(['ON', 'DELETE', 'DEFAULT'], array_column(PragmaKeyword::cases(), 'value'));
    }
}
