<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GrammarAlternativeSnapshot;
use SqlFaker\Contract\GrammarRuleSnapshot;
use SqlFaker\Contract\GrammarSymbolSnapshot;

#[CoversClass(GrammarRuleSnapshot::class)]
final class GrammarRuleSnapshotTest extends TestCase
{
    public function testConstructsReadonlyGrammarRuleSnapshot(): void
    {
        $rule = new GrammarRuleSnapshot('stmt', [
            new GrammarAlternativeSnapshot([
                new GrammarSymbolSnapshot('SELECT', false),
            ]),
        ]);

        self::assertSame('stmt', $rule->name);
        self::assertCount(1, $rule->alternatives);
        self::assertSame(['t:SELECT'], $rule->alternatives[0]->sequence());
    }
}
