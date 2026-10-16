<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Lowering\Routine\DefinitionBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(DefinitionBody::class)]
#[Small]
final class DefinitionBodyTest extends TestCase
{
    public function testCheckRetainsTheGeneratedParameterCounterexampleAsAnInputRefusal(): void
    {
        try {
            (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze('CREATE FUNCTION `10`.`2`(`あ` DECIMAL, utf8mb4_0900_ai_ci DECIMAL, `0` DECIMAL) RETURNS MEDIUMBLOB RETURN !! JSON_OBJECTAGG(0, ?)');
            self::fail('A routine body cannot retain an unscoped client parameter.');
        } catch (RejectedInput $error) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody::class, $error->problem);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ParameterScopeMismatch::class, $error->problem->problem);
            self::assertSame('10', $error->problem->name->schema?->value);
        }
    }

    public function testLanguageKeepsAnExternalBodyLocationWithoutPublishingTheDeclaration(): void
    {
        try {
            (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.1.0'))->analyze("CREATE FUNCTION f() RETURNS INT AS 'body'");
            self::fail('The SQL language cannot contain an external body.');
        } catch (RejectedInput $error) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody::class, $error->problem);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch::class, $error->problem->problem);
            self::assertSame(35, $error->problem->problem->offset);
        }
    }

    public function testCheckRefusesAFunctionWithoutReturn(): void
    {
        $this->expectException(RejectedInput::class);
        $this->expectExceptionMessage('No RETURN found in FUNCTION f');
        (new DefinitionBody())->check(new Block(), ProgramKind::Function, new QualifiedName(new Name('f')), null, 80);
    }

    public function testTypeEndsPublishesOnlyCompletedDeclarationBoundaries(): void
    {
        try {
            (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE FUNCTION f(a INT,b INT) RETURNS INT BEGIN END');
            self::fail('A function without RETURN must not be published.');
        } catch (RejectedInput $error) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody::class, $error->problem);
            self::assertSame([23, 29, 42], $error->problem->typeEnds);
        }
    }
}
