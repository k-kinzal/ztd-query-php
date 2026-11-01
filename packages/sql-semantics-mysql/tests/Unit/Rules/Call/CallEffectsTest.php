<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Call\CallEffects;

#[CoversClass(CallEffects::class)]
#[Medium]
final class CallEffectsTest extends TestCase
{
    public function testPerReadKeepsArgumentSensitiveEffectsSeparateFromPureCalls(): void
    {
        $effects = new CallEffects();

        self::assertTrue($effects->perRead('rand', 1));
        self::assertTrue($effects->perRead('last_insert_id', 1));
        self::assertFalse($effects->perRead('LAST_INSERT_ID', 0));
        self::assertFalse($effects->perRead('ABS', 1));
    }

    public function testUncacheableDistinguishesEffectsFromStatementConstants(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT RAND(1), @v, @v:=1, SYSDATE(), NOW(), LAST_INSERT_ID(1), LAST_INSERT_ID(), ABS(1), d.f(1), custom_function(1)');
        $effects = new CallEffects();
        $values = array_map(static function (int $position) use ($operation, $effects): bool {
            $expression = $operation->field($position)->expression;
            self::assertNotNull($expression);

            return $effects->uncacheable($expression, GrammarRelease::MySql847);
        }, range(0, 9));

        self::assertSame([true, true, true, true, false, true, false, false, true, true], $values);
    }
}
