<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Server\ForeignStrings;
use SqlSemantics\Platform\MySql\Statement\Notice\NameConversion;

#[CoversClass(ForeignStrings::class)]
#[Medium]
final class ForeignStringsTest extends TestCase
{
    public function testDerivePreservesEveryWrittenOperandBeforeReplacement(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE SERVER s FOREIGN DATA WRAPPER '😀' OPTIONS(HOST '😀', HOST 'localhost', DATABASE '猫😀')");

        self::assertCount(3, $operation->facts->warnings);
        self::assertSame(['😀', '😀', '猫😀'], array_map(static fn (\SqlSemantics\Statement\Fact\Warning $warning): string => $warning instanceof NameConversion ? $warning->name->value : '', $operation->facts->warnings));
    }

    public function testConversionKeepsTheFullValueAndSelectsTheReleaseDiagnostic(): void
    {
        $rule = new ForeignStrings();

        self::assertNull($rule->conversion('猫é', GrammarRelease::MySql847));
        self::assertSame("Cannot convert string 'abc\\xF0\\x9F\\x98...' from utf8mb4 to utf8mb3", $rule->conversion('abc😀def', GrammarRelease::MySql847)?->message());
        self::assertSame("Invalid utf8mb4 character string: 'abc\\xF0\\x9F\\x98...'", $rule->conversion('abc😀def', GrammarRelease::MySql5744)?->message());
    }
}
