<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Signatures;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Signatures::class)]
#[Small]
final class SignaturesTest extends TestCase
{
    public function testCompatibleIntersectsCallAndGrammarDomains(): void
    {
        $rule = new Signatures();
        $x = new FunctionCall(new Name('X'));
        $regexp = new FunctionCall(new Name('REGEXP_LIKE'));
        $jsonLength = new FunctionCall(new Name('JSON_LENGTH'));

        self::assertTrue($rule->compatible([], 0, 0, null));
        self::assertTrue($rule->compatible([$x], 2, 0, null));
        self::assertFalse($rule->compatible([$x], 1, 0, null));
        self::assertTrue($rule->compatible([$regexp], 1, 0, null));
        self::assertFalse($rule->compatible([$regexp], 2, 0, null));
        self::assertFalse($rule->compatible([$x, $regexp], 0, 0, null));
        self::assertTrue($rule->compatible([$jsonLength], 0, 1, null));
        self::assertFalse($rule->compatible([$jsonLength], 0, 2, null));
        self::assertFalse($rule->compatible([$x], 0, 1, null));
        self::assertTrue($rule->compatible([$x], 0, 2, null));
        self::assertFalse($rule->compatible([$x], 0, 0, new LanguageProfile(GrammarRelease::MySql5744)));
        self::assertTrue($rule->compatible([new FunctionCall(new Name('X'), schema: new Name('d')), $regexp], 1, 0, null));
    }
}
