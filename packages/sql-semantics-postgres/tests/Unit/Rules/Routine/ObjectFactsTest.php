<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorArity;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorSignature;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ObjectFacts::class)]
#[Small]
final class ObjectFactsTest extends TestCase
{
    public function testDeriveReportsAPostfixOperatorWithoutIfExists(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $facts = (new ObjectFacts())->derive(ObjectKind::Operator, [new OperatorSignature(new OperatorName(new Name('!')), OperatorArity::Postfix, [new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))])], false, $derivation);
        (new ObjectFacts())->derive(ObjectKind::Operator, [new OperatorSignature(new OperatorName(new Name('!')), OperatorArity::Postfix, [new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))])], true, $derivation);
        self::assertSame([null], $facts);
        self::assertEquals([new RoutineProblem(RoutineProblemKind::PostfixOperator)], $derivation->facts()->diagnostics);
    }
}
