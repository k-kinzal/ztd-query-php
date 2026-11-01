<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\IllegalCollationMix;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;

#[CoversClass(IllegalCollationMix::class)]
#[Small]
final class IllegalCollationMixTest extends TestCase
{
    public function testMessageNamesTwoOrThreeOperandsAndNoMore(): void
    {
        $a = ['utf8mb4_bin', Coercibility::Explicit];
        $b = ['utf8mb4_general_ci', Coercibility::Explicit];
        $c = ['utf8mb4_0900_ai_ci', Coercibility::Coercible];

        self::assertSame("Illegal mix of collations (utf8mb4_bin,EXPLICIT) and (utf8mb4_general_ci,EXPLICIT) for operation '='", (new IllegalCollationMix([$a, $b], '='))->message());
        self::assertSame("Illegal mix of collations (utf8mb4_bin,EXPLICIT), (utf8mb4_general_ci,EXPLICIT), (utf8mb4_0900_ai_ci,COERCIBLE) for operation 'between'", (new IllegalCollationMix([$a, $b, $c], 'between'))->message());
        self::assertSame("Illegal mix of collations for operation ' IN '", (new IllegalCollationMix([$a, $b, $c, $c], ' IN '))->message());
    }

    public function testLevelNamesEachCoercibilityAsTheServerDoes(): void
    {
        self::assertSame(['EXPLICIT', 'NONE', 'IMPLICIT', 'SYSCONST', 'COERCIBLE', 'NUMERIC', 'IGNORABLE'], array_map(IllegalCollationMix::level(...), Coercibility::cases()));
    }
}
