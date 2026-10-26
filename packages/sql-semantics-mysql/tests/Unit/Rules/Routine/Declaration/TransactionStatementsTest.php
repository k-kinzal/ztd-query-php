<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\TransactionStatements;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

#[CoversClass(TransactionStatements::class)]
#[Small]
final class TransactionStatementsTest extends TestCase
{
    /**
     * @return iterable<string, array{Node, bool}>
     */
    public static function providerInstructions(): iterable
    {
        $name = new QualifiedName(new Name('x'));
        yield 'tablespace' => [new M\Server\Storage\AlterTablespace(new Name('x'), [new M\Server\Storage\Option\WaitOption(false)]), true];
        yield 'columnless' => [new M\Table\CreateTable($name), true];
        yield 'temporary-columnless' => [new M\Table\CreateTable($name, temporaryWords: 1), false];
        yield 'like' => [new M\Table\CreateTableLike($name, new QualifiedName(new Name('y'))), true];
        yield 'temporary-like' => [new M\Table\CreateTableLike($name, new QualifiedName(new Name('y')), temporaryWords: 1), false];
        yield 'instance TLS' => [new M\Server\Instance\AlterInstance(new M\Server\Instance\ReloadTls()), true];
        yield 'block' => [new M\Routine\Program\Block(), false];
    }

    #[DataProvider('providerInstructions')]
    public function testImplicitDistinguishesPermanentAndTemporaryInstructions(Node $instruction, bool $implicit): void
    {
        self::assertSame($implicit, (new TransactionStatements())->implicit($instruction));
    }

    public function testImplicitRejectsAnUnreachableTablespaceInstructionInATrigger(): void
    {
        $body = new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Literal\NumberLiteral('0'), [new M\Server\Storage\AlterTablespace(new Name('x'), [new M\Server\Storage\Option\WaitOption(false)])])]);
        $this->expectExceptionMessage('Explicit or implicit commit is not allowed in stored function or trigger.');
        new M\Routine\CreateTrigger(new QualifiedName(new Name('tr')), M\Routine\Trigger\TriggerTime::Before, M\Routine\Trigger\TriggerEvent::Update, new M\Routine\Trigger\TriggerTable(new QualifiedName(new Name('t'))), $body);
    }
    public function testImplicitRejectsAnInstanceActionEvenInsideAnUnreachableTriggerBranch(): void
    {
        $body = new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Literal\NumberLiteral('0'), [new M\Server\Instance\AlterInstance(new M\Server\Instance\ReloadTls())])]);
        $this->expectExceptionMessage('Explicit or implicit commit is not allowed in stored function or trigger.');
        new M\Routine\CreateTrigger(new QualifiedName(new Name('tr')), M\Routine\Trigger\TriggerTime::Before, M\Routine\Trigger\TriggerEvent::Insert, new M\Routine\Trigger\TriggerTable(new QualifiedName(new Name('t'))), $body);
    }

}
