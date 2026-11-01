<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;

#[CoversClass(ExplainModifier::class)]
#[Small]
final class ExplainModifierTest extends TestCase
{
    public function testCasesSpellTheKeywords(): void
    {
        self::assertSame(['EXTENDED', 'PARTITIONS'], array_column(ExplainModifier::cases(), 'value'));
    }
    public function testDeprecationBelongsToMySql57Only(): void
    {
        self::assertNull(ExplainModifier::Extended->deprecation(\SqlSemantics\Contract\GrammarRelease::MySql5651));
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::ExplainExtended, ExplainModifier::Extended->deprecation(\SqlSemantics\Contract\GrammarRelease::MySql5744));
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::ExplainPartitions, ExplainModifier::Partitions->deprecation(\SqlSemantics\Contract\GrammarRelease::MySql5744));
        self::assertNull(ExplainModifier::Partitions->deprecation(\SqlSemantics\Contract\GrammarRelease::MySql847));
    }

}
