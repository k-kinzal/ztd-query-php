<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\NamedCondition;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(NamedCondition::class)]
#[Medium]
final class NamedConditionTest extends TestCase
{
    public function testRequiresAName(): void
    {
        $this->expectException(InvalidStructure::class);
        new NamedCondition('');
    }

    public function testDiagnosesAnUndeclaredCondition(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramObject->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR missing BEGIN END; END', strict: false);
    }
}
