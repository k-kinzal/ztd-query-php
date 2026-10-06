<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Alter\ModifierRule;

#[CoversClass(ModifierRule::class)]
#[Medium]
final class ModifierRuleTest extends TestCase
{
    public function testOptionLowersTheValue(): void
    {
        self::assertSame('ALTER TABLE t ALGORITHM = INSTANT', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ALGORITHM INSTANT')->toString());
    }

    public function testValueLowersDefault(): void
    {
        self::assertSame('ALTER TABLE t LOCK = DEFAULT', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t LOCK DEFAULT')->toString());
    }

    public function testPairLowersTheOptionsInOrder(): void
    {
        self::assertSame('DROP INDEX i ON t LOCK = SHARED ALGORITHM = COPY', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('DROP INDEX i ON t LOCK SHARED ALGORITHM COPY')->toString());
    }

    public function testModifiersLowersTheList(): void
    {
        self::assertSame('ALTER TABLE t ALGORITHM = COPY, LOCK = SHARED, DROP PARTITION p', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ALGORITHM = COPY, LOCK = SHARED, DROP PARTITION p')->toString());
    }

    public function testModifierLowersValidation(): void
    {
        self::assertSame('ALTER TABLE t WITHOUT VALIDATION', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t WITHOUT VALIDATION')->toString());
    }

    public function testValidationLowersTheOptionOfExchange(): void
    {
        self::assertSame('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE u WITH VALIDATION', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE u WITH VALIDATION')->toString());
    }
}
