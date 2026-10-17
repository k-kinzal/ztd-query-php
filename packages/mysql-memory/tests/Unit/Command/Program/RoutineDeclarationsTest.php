<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\RoutineDeclarations;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RoutineDeclarations::class)]
#[Small]
final class RoutineDeclarationsTest extends TestCase
{
    public function testItemsPreservesParameterAndReturnDeclarationOrder(): void
    {
        $parameter = new Enumeration(EnumerationKind::Enum, [new Text('a')]);
        $returns = new Enumeration(EnumerationKind::Set, [new Text('b')]);
        $refusal = new RefusedProgramBody(new QualifiedName(new Name('f')), ProgramKind::Function, new ProgramProblem(ProgramRule::MissingReturn, 'f'), parameters: new ParameterList([new Parameter(new Name('x'), $parameter)]), returns: $returns);
        self::assertSame([[$parameter, null], [$returns, null]], (new RoutineDeclarations())->items($refusal));
    }

    public function testCheckRecordsDuplicateMembersInDeclarationOrder(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query("SET sql_mode=''");
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $enum = new Enumeration(EnumerationKind::Enum, [new Text('a'), new Text('a')]);
        $set = new Enumeration(EnumerationKind::Set, [new Text('b'), new Text('b')]);
        (new RoutineDeclarations())->check(new QualifiedName(new Name('f')), [[$enum, null], [$set, null]], $session, $context);
        self::assertSame([['Note', 1291, "Column '' has duplicated value 'a' in ENUM"], ['Note', 1291, "Column '' has duplicated value 'b' in SET"]], $session->diagnostics->conditions);
    }
}
