<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Collations;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\IllegalCollationMix;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Collations::class)]
#[Small]
final class CollationsTest extends TestCase
{
    public function testAggregateReportsAConflictWithEveryOperand(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $left = Domain::string(5, Collation::known('utf8mb4_general_ci'));
        $right = Domain::string(5, Collation::known('utf8mb4_unicode_ci'));

        self::assertNull(new Collations(Collation::known('utf8mb4_0900_ai_ci'))->aggregate([$left, $right], '=', $derivation, true));
        self::assertEquals([new IllegalCollationMix([['utf8mb4_general_ci', Coercibility::Implicit], ['utf8mb4_unicode_ci', Coercibility::Implicit]], '=')], $derivation->facts()->diagnostics);
    }

    public function testSettleLetsTheStrongestCollationWin(): void
    {
        $literal = Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible);

        self::assertEquals([Collation::known('latin1_bin'), Coercibility::Implicit], new Collations(Collation::known('utf8mb4_0900_ai_ci'))->settle([$literal, Domain::string(5, Collation::known('latin1_bin'))]));
        self::assertEquals([Collation::known('utf8mb4_0900_ai_ci'), Coercibility::Numeric], new Collations(Collation::known('utf8mb4_0900_ai_ci'))->settle([Domain::integer()]));
    }

    public function testSettleGivesNoCoercibilityToTwoCollationsOfOneSetOutsideAComparison(): void
    {
        $operands = [Domain::string(5, Collation::known('utf8mb4_general_ci')), Domain::string(5, Collation::known('utf8mb4_unicode_ci'))];

        self::assertEquals([Collation::known('utf8mb4_bin'), Coercibility::None], new Collations(Collation::known('utf8mb4_0900_ai_ci'))->settle($operands));
        self::assertNull(new Collations(Collation::known('utf8mb4_0900_ai_ci'))->settle($operands, true));
    }

    public function testOperandWritesNumbersInTheConnectionCollation(): void
    {
        self::assertEquals([Collation::known('utf8mb4_0900_ai_ci'), Coercibility::Numeric], new Collations(Collation::known('utf8mb4_0900_ai_ci'))->operand(Domain::integer()));
        self::assertEquals([Collation::known('utf8mb4_0900_ai_ci'), Coercibility::Ignorable], new Collations(Collation::known('utf8mb4_0900_ai_ci'))->operand(Domain::null()));
    }

    public function testTieFollowsTheRulesOfEqualCoercibility(): void
    {
        $rules = new Collations(Collation::known('utf8mb4_0900_ai_ci'));

        self::assertNull($rules->tie(Collation::known('utf8mb4_bin'), Collation::known('utf8mb4_general_ci'), Coercibility::Explicit));
        self::assertEquals([Collation::binary(), Coercibility::Implicit], $rules->tie(Collation::binary(), Collation::known('latin1_bin'), Coercibility::Implicit));
        self::assertEquals([Collation::known('utf8mb4_bin'), Coercibility::Implicit], $rules->tie(Collation::known('utf8mb4_general_ci'), Collation::known('utf8mb4_bin'), Coercibility::Implicit));
        self::assertEquals([Collation::known('utf8mb4_bin'), Coercibility::Implicit], $rules->tie(Collation::known('latin1_swedish_ci'), Collation::known('utf8mb4_bin'), Coercibility::Implicit));
        self::assertNull($rules->tie(Collation::known('latin1_swedish_ci'), Collation::known('ascii_general_ci'), Coercibility::Implicit));
    }

    public function testTieLetsUtf8mb4WinOverTheOtherUnicodeSets(): void
    {
        $rules = new Collations(Collation::known('utf8mb4_0900_ai_ci'));

        self::assertEquals([Collation::known('utf8mb4_0900_ai_ci'), Coercibility::Coercible], $rules->tie(Collation::known('utf8mb3_general_ci'), Collation::known('utf8mb4_0900_ai_ci'), Coercibility::Coercible));
        self::assertEquals([Collation::known('utf8mb4_0900_ai_ci'), Coercibility::Implicit], $rules->tie(Collation::known('utf8mb4_0900_ai_ci'), Collation::known('utf16_general_ci'), Coercibility::Implicit));
        self::assertNull($rules->tie(Collation::known('utf8mb3_general_ci'), Collation::known('utf16_general_ci'), Coercibility::Implicit));
    }
}
