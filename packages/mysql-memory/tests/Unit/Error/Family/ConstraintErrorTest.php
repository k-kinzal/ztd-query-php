<?php

declare(strict_types=1);

namespace Tests\Unit\Error\Family;

use MySqlMemory\Error\Family\ConstraintError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConstraintError::class)]
#[Small]
final class ConstraintErrorTest extends TestCase
{
    public function testErrorAnswersTheNumberStateAndMessage(): void
    {
        $error = ConstraintError::DropReferencedTable->error('p', 'c_ibfk_1', 'c');

        self::assertSame([3730, 'HY000', "Cannot drop table 'p' referenced by a foreign key constraint 'c_ibfk_1' on table 'c'."], [$error->getCode(), ConstraintError::DropReferencedTable->sqlState(), $error->getMessage()]);
    }

    public function testNumberAnswersTheErrorNumber(): void
    {
        self::assertSame([1215, 1451], [ConstraintError::CannotAddForeign->number(), ConstraintError::RowIsReferenced->number()]);
    }

    public function testMessageFillsTheConstraintIntoTheFormat(): void
    {
        self::assertSame('Cannot add or update a child row: a foreign key constraint fails ((`d`.`c`, CONSTRAINT `fk`))', ConstraintError::NoReferencedRow->message('(`d`.`c`, CONSTRAINT `fk`)'));
    }

    public function testSqlStateAnswersTheStateOfAViolatedKey(): void
    {
        self::assertSame(['23000', '23000'], [ConstraintError::NoReferencedRow->sqlState(), ConstraintError::RowIsReferenced->sqlState()]);
    }
}
