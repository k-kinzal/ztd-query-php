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
     * @return iterable<string, array{string, string}>
     */
    public static function providerInputs(): iterable
    {
        yield 'outer alias first' => ['CREATE PROCEDURE p() SELECT 1 FROM a,a,(SELECT 1 FROM b,b) c', 'b'];
        yield 'inner alias first' => ['CREATE PROCEDURE p() SELECT 1 FROM a,(SELECT 1 FROM b,b) c,a', 'b'];
        yield 'alias before condition' => ['CREATE PROCEDURE p() BEGIN SELECT 1 FROM a,a; SIGNAL missing_condition; END', 'a'];
        yield 'nested input before assignment scope' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT; SET GLOBAL x=(SELECT 1 FROM a,a); END', 'a'];
    }

    #[DataProvider('providerInputs')]
    public function testFirstIsNeverReachedForARepeatedInputNameTheReadingRefuses(string $sql, string $alias): void
    {
        try {
            (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze($sql);
            self::fail('The body names a relation twice.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(M\Server\Problem\NonUniqueTable::class, $failure->problem);
            self::assertSame($alias, $failure->problem->alias->value);
        }
    }

    public function testFirstLeavesOrdinaryBodiesWithoutAnInputConstraint(): void
    {
        self::assertNull((new BodyInputs())->first(new M\Routine\Program\Block(), [], null));
    }

    public function testActivationIsNeverReachedForARepeatedCommonTableInput(): void
    {
        try {
            (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('CREATE PROCEDURE p() WITH c AS(SELECT 1 FROM a,a) SELECT SQL_NO_CACHE * FROM c');
            self::fail('The common table names a relation twice.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(M\Server\Problem\NonUniqueTable::class, $failure->problem);
        }
    }
}
