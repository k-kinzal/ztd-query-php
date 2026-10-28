<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\Definition\BodyInputs;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(BodyInputs::class)]
#[Medium]
final class BodyInputsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerInputs(): iterable
    {
        yield 'outer alias first' => ['CREATE PROCEDURE p() SELECT 1 FROM a,a,(SELECT 1 FROM b,b) c', "Not unique table/alias: 'a'.", 'FROM a,a'];
        yield 'inner alias first' => ['CREATE PROCEDURE p() SELECT 1 FROM a,(SELECT 1 FROM b,b) c,a', "Not unique table/alias: 'b'.", 'FROM b,b'];
        yield 'condition first' => ['CREATE PROCEDURE p() BEGIN SIGNAL missing_condition; SELECT 1 FROM a,a; END', 'Undefined CONDITION: missing_condition', 'missing_condition'];
        yield 'alias before condition' => ['CREATE PROCEDURE p() BEGIN SELECT 1 FROM a,a; SIGNAL missing_condition; END', "Not unique table/alias: 'a'.", 'FROM a,a'];
        yield 'local scope first' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT; SET GLOBAL x=1; SELECT 1 FROM a,a; END', 'A local variable assignment cannot carry a system variable scope.', 'GLOBAL x=1'];
        yield 'nested input before assignment scope' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT; SET GLOBAL x=(SELECT 1 FROM a,a); END', "Not unique table/alias: 'a'.", 'FROM a,a'];
    }

    #[DataProvider('providerInputs')]
    public function testFirstRetainsTheReachedConditionAndBoundary(string $sql, string $message, string $boundary): void
    {
        $statement = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze($sql);
        self::assertInstanceOf(M\Statement::class, $statement);
        $notices = array_values(array_filter($statement->statementInformation()->notices, static fn ($notice): bool => $notice->warning instanceof M\Notice\ParseFailure));
        self::assertCount(1, $notices);
        $warning = $notices[0]->warning;
        self::assertInstanceOf(M\Notice\ParseFailure::class, $warning);
        self::assertSame($message, $warning->message());
        self::assertTrue($warning->aborts);
        $position = strpos($sql, $boundary);
        self::assertNotFalse($position);
        self::assertSame($position + strlen($boundary), $notices[0]->inputSource?->offset);
    }

    public function testFirstLeavesOrdinaryBodiesWithoutAnInputConstraint(): void
    {
        self::assertNull((new BodyInputs())->first(new M\Routine\Program\Block(), [], null));
    }

    public function testActivationRetainsTheLaterCommonTableReference(): void
    {
        $sql = 'CREATE PROCEDURE p() WITH c AS(SELECT 1 FROM a,a) SELECT SQL_NO_CACHE * FROM c';
        $statement = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze($sql);
        self::assertInstanceOf(M\Statement::class, $statement);
        $notices = array_values(array_filter($statement->statementInformation()->notices, static fn ($notice): bool => $notice->warning instanceof M\Notice\ParseFailure));
        self::assertCount(1, $notices);
        self::assertSame("Not unique table/alias: 'a'.", $notices[0]->warning->message());
        self::assertSame(strlen($sql), $notices[0]->inputSource?->offset);
    }
}
