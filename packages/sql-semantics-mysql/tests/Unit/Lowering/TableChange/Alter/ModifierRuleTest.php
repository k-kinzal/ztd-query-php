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
    public function testCompletedRetainsTheFirstUnknownOptionBeforeLaterSyntax(): void
    {
        $parser = new \SqlParser\MySql\MySqlParser('mysql-8.4.7');
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847);
        $sql = 'ALTER TABLE t ALGORITHM=bad TRAILING';
        $notices = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->completed($parser, $sql, strlen($sql), $profile);
        self::assertCount(1, $notices);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $notices[0][1]);
        self::assertTrue($notices[0][1]->aborts);
        self::assertSame('bad is not an ALGORITHM the server knows.', $notices[0][1]->problem->message());
    }

    public function testOptionRetainsUnknownInputWithItsCondition(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ALGORITHM=bad');
        self::assertSame('ALTER TABLE t ALGORITHM = bad', $statement->toString());
        self::assertSame('bad is not an ALGORITHM the server knows.', $statement->diagnostics()[0]->message());
    }

    public function testOptionRetainsANameAbsentFromTheRelease(): void
    {
        $statement = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t ALGORITHM=INSTANT');
        self::assertSame('ALTER TABLE t ALGORITHM = INSTANT', $statement->toString());
        self::assertSame('INSTANT is not an ALGORITHM the server knows.', $statement->diagnostics()[0]->message());
    }

    public function testOptionLowersTheValue(): void
    {
        self::assertSame('ALTER TABLE t ALGORITHM INSTANT', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ALGORITHM INSTANT')->toString());
    }

    public function testValueLowersDefault(): void
    {
        self::assertSame('ALTER TABLE t LOCK DEFAULT', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t LOCK DEFAULT')->toString());
    }

    public function testPairLowersTheOptionsInOrder(): void
    {
        self::assertSame('DROP INDEX i ON t LOCK SHARED ALGORITHM COPY', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('DROP INDEX i ON t LOCK SHARED ALGORITHM COPY')->toString());
    }

    public function testModifiersLowersTheList(): void
    {
        self::assertSame('ALTER TABLE t ALGORITHM = COPY, LOCK = SHARED, DROP PARTITION p', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ALGORITHM = COPY, LOCK = SHARED, DROP PARTITION p')->toString());
    }

    public function testModifierLowersValidation(): void
    {
        self::assertSame('CREATE PROCEDURE p() ALTER TABLE t WITHOUT VALIDATION', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() ALTER TABLE t WITHOUT VALIDATION')->toString());
    }

    public function testModifierRetainsTheStandaloneValidationCondition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t WITHOUT VALIDATION');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable::class, $operation);
        self::assertTrue($operation->hasExecutionCondition());
    }

    public function testValidationLowersTheOptionOfExchange(): void
    {
        self::assertSame('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE u WITH VALIDATION', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE u WITH VALIDATION')->toString());
    }
}
