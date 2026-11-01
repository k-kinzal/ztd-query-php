<?php

declare(strict_types=1);

namespace Tests\Unit\Typing;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Typing\Collations;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Collations::class)]
#[Small]
final class CollationsTest extends TestCase
{
    public function testAggregateTakesTheCollationOfTheLowestCoercibility(): void
    {
        $implicit = Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'));
        $explicit = Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'))->withCollation(Collation::known('utf8mb4_bin'), Coercibility::Explicit);

        [$collation, $coercibility] = Collations::aggregate([$implicit, $explicit], 'concat', Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame(['utf8mb4_bin', Coercibility::Explicit], [$collation->name, $coercibility]);
    }

    public function testAggregatePrefersTheBinCollationOfTheSameCharacterSet(): void
    {
        $swedish = Domain::string(1, Collation::known('latin1_swedish_ci'));
        $binary = Domain::string(1, Collation::known('latin1_bin'));

        [$collation, $coercibility] = Collations::aggregate([$swedish, $binary], '=', Collation::known('utf8mb4_0900_ai_ci'), true);

        self::assertSame(['latin1_bin', Coercibility::Implicit], [$collation->name, $coercibility]);
    }

    public function testAggregateRefusesToCompareTwoCollationsOfOneLevel(): void
    {
        $swedish = Domain::string(1, Collation::known('latin1_swedish_ci'));
        $german = Domain::string(1, Collation::known('latin1_german1_ci'));

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1267);
        $this->expectExceptionMessage("Illegal mix of collations (latin1_swedish_ci,IMPLICIT) and (latin1_german1_ci,IMPLICIT) for operation '='");

        Collations::aggregate([$swedish, $german], '=', Collation::known('utf8mb4_0900_ai_ci'), true);
    }

    public function testAggregateNamesThreeConflictingOperands(): void
    {
        $swedish = Domain::string(1, Collation::known('latin1_swedish_ci'));
        $german = Domain::string(1, Collation::known('latin1_german1_ci'));

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1270);
        $this->expectExceptionMessage("Illegal mix of collations (latin1_swedish_ci,IMPLICIT), (latin1_german1_ci,IMPLICIT), (latin1_german1_ci,IMPLICIT) for operation 'in'");

        Collations::aggregate([$swedish, $german, $german], 'in', Collation::known('utf8mb4_0900_ai_ci'), true);
    }

    public function testAggregateNamesNoOperandsAboveThree(): void
    {
        $swedish = Domain::string(1, Collation::known('latin1_swedish_ci'));
        $german = Domain::string(1, Collation::known('latin1_german1_ci'));

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1271);
        $this->expectExceptionMessage("Illegal mix of collations for operation 'in'");

        Collations::aggregate([$swedish, $german, $german, $german], 'in', Collation::known('utf8mb4_0900_ai_ci'), true);
    }

    public function testAggregateSettlesExplicitCollationsOfDifferentSetsIn57(): void
    {
        $domains = [Domain::string(1, Collation::known('utf8mb3_bin'))->withCollation(Collation::known('utf8mb3_bin'), Coercibility::Explicit), Domain::string(1, Collation::known('latin1_bin'))->withCollation(Collation::known('latin1_bin'), Coercibility::Explicit)];

        self::assertSame('utf8mb3_bin', Collations::aggregate($domains, '=', Collation::known('latin1_swedish_ci'), true, GrammarRelease::MySql5744)[0]->name);
    }
}
