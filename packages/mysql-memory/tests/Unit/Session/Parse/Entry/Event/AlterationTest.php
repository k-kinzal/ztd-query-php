<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry\Event;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\Event\Alteration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Alteration::class)]
#[Small]
final class AlterationTest extends TestCase
{
    public function testErrorRequiresTheDefaultDatabaseBeforeTheEmptyChange(): void
    {
        $this->expectExceptionMessage('No database selected');

        (new Alteration())->error(new EmptyEventAlteration(new QualifiedName(new Name('e'))), (new Instance())->connect());
    }

    public function testErrorUsesTheExplicitDatabaseWithoutLookingUpTheEvent(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'ALTER EVENT absent.e';
        $problem = new EmptyEventAlteration(new QualifiedName(new Name('e'), new Name('absent')), offset: strlen($session->text));

        self::assertSame(1064, (new Alteration())->error($problem, $session)->getCode());
    }

    public function testSyntaxUsesTheRefusedOperandBoundaryInsideAProgram(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'ALTER EVENT e; END';
        $problem = new EmptyEventAlteration(new QualifiedName(new Name('e')), offset: 13);

        self::assertStringContainsString("near '; END' at line 1", (new Alteration())->syntax($problem, $session)->getMessage());
    }

    public function testOperandRequiresTheDefaultDatabaseBeforeItsWidthCondition(): void
    {
        $this->expectExceptionMessage('No database selected');

        (new Alteration())->operand(new \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\RefusedOperand(new QualifiedName(new Name('e')), new \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns(1, 2), \SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause::At, true)), (new Instance())->connect());
    }

    public function testOperandUsesTheQualifiedHeaderWithoutReadingRequestText(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $problem = new \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\RefusedOperand(new QualifiedName(new Name('e'), new Name('absent')), new \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns(1, 2), \SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause::At, true));

        self::assertSame(1241, (new Alteration())->operand($problem, $session)->getCode());
        self::assertSame('unrelated invalid input', $session->text);
    }
}
