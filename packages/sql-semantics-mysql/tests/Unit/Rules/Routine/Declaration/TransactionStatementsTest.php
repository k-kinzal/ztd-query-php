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
        $column = static fn (): M\Table\Column\ColumnDefinition => new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        yield 'table' => [new M\Table\CreateTable($name, [$column()]), true];
        yield 'temporary table' => [new M\Table\CreateTable($name, [$column()], temporaryWords: 1), false];
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
        $refusal = (new \SqlSemantics\Platform\MySql\Rules\Routine\Definition\BodyConditions())->refusal($body, M\Routine\ProgramKind::Trigger, new QualifiedName(new Name('tr')), null);
        self::assertNotNull($refusal);
        self::assertSame('Explicit or implicit commit is not allowed in stored function or trigger.', $refusal[0]->message());
    }
    public function testImplicitRejectsAnInstanceActionEvenInsideAnUnreachableTriggerBranch(): void
    {
        $body = new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Literal\NumberLiteral('0'), [new M\Server\Instance\AlterInstance(new M\Server\Instance\ReloadTls())])]);
        $refusal = (new \SqlSemantics\Platform\MySql\Rules\Routine\Definition\BodyConditions())->refusal($body, M\Routine\ProgramKind::Trigger, new QualifiedName(new Name('tr')), null);
        self::assertNotNull($refusal);
        self::assertSame('Explicit or implicit commit is not allowed in stored function or trigger.', $refusal[0]->message());
    }

}
