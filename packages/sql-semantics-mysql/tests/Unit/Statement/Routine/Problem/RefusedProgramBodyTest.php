<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RefusedProgramBody::class)]
#[Small]
final class RefusedProgramBodyTest extends TestCase
{
    public function testMessageDescribesTheInstructionConstraint(): void
    {
        $problem = new ProgramProblem(ProgramRule::CommitInFunction);
        $refusal = new RefusedProgramBody(new QualifiedName(new Name('f')), ProgramKind::Function, $problem);
        self::assertSame($problem->message(), $refusal->message());
    }
}
