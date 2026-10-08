<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\ProgramErrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ProgramErrors::class)]
#[Small]
final class ProgramErrorsTest extends TestCase
{
    public function testErrorKeepsTheMessageOfTheProblem(): void
    {
        $error = (new ProgramErrors())->error(new ProgramProblem(ProgramRule::UndefinedCondition, 'PROCESS'));

        self::assertSame([1319, '42000', 'Undefined CONDITION: PROCESS'], [$error->getCode(), $error->sqlState(), $error->getMessage()]);
    }

    public function testRelationNamesTheObjectTheStatementNeeds(): void
    {
        $errors = new ProgramErrors();

        self::assertSame(["'d.t' is not VIEW", "Unknown table 'e.v'"], [$errors->relation(new WrongRelationKind(new QualifiedName(new Name('t')), KindRefusal::NotView), 'd')->getMessage(), $errors->relation(new WrongRelationKind(new QualifiedName(new Name('v'), new Name('e')), KindRefusal::UnknownTable), 'd')->getMessage()]);
    }

    public function testCodesHoldEveryRule(): void
    {
        self::assertSame(array_map(static fn (ProgramRule $rule): string => $rule->name, ProgramRule::cases()), array_keys(ProgramErrors::CODES));
    }

    public function testCodeAnswersTheNumberOfEachRule(): void
    {
        $errors = new ProgramErrors();

        self::assertSame([ProgramError::BadSqlState, ProgramError::DuplicateSignalItem, ProgramError::LabelMissing, ProgramError::TriggerRowChange], [$errors->code(ProgramRule::BadSqlState), $errors->code(ProgramRule::DuplicateSignalItem), $errors->code(ProgramRule::IterateWithoutLabel), $errors->code(ProgramRule::AfterRowUpdate)]);
    }
}
