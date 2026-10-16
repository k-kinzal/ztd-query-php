<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Leaf\WordRule;

#[CoversClass(WordRule::class)]
#[Medium]
final class WordRuleTest extends TestCase
{
    public function testWordKeepsNamesAndLeavesCompoundExpressionsToExpressionLowering(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        self::assertSame('SET @@sql_mode = TRADITIONAL', $semantics->analyze('SET @@sql_mode=TRADITIONAL')->toString());
        self::assertSame("INSTALL COMPONENT 'c' SET x = d.word, y = 1 + 2", $semantics->analyze("INSTALL COMPONENT 'c' SET x=d.word, y=1+2")->toString());
    }
}
