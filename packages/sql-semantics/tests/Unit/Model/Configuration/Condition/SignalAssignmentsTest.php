<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Condition\SignalAssignments;
use SqlSemantics\Model\Statement\Procedural\SignalStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SignalAssignments::class)]
#[Medium]
final class SignalAssignmentsTest extends TestCase
{
    public function testValidateRejectsARepeatedItem(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'a'");
        self::assertInstanceOf(SignalStatement::class, $statement);
        SignalAssignments::validate($statement->assignments);
        $this->expectException(InvalidStructure::class);
        SignalAssignments::validate([$statement->assignments[0], $statement->assignments[0]]);
    }
}
