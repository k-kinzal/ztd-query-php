<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Declaration;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Typing\Declaration\FixedSizeBounds;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(FixedSizeBounds::class)]
#[Small]
final class FixedSizeBoundsTest extends TestCase
{
    public function testTypeDistinguishesCommonBoundsFromLegacyInterpretations(): void
    {
        $rules = new FixedSizeBounds();
        self::assertNull($rules->type(new M\Type\Temporal(M\Type\Kind\TemporalKind::DateTime, '6'), 'a'));
        self::assertNull($rules->type(new M\Type\Elementary(M\Type\Kind\ElementaryKind::Bit, '0'), 'a'));
        self::assertSame(M\Type\Problem\TypeLimit::Precision, $rules->type(new M\Type\Temporal(M\Type\Kind\TemporalKind::DateTime, '7'), 'a')?->rule);
        self::assertSame(M\Type\Problem\TypeLimit::Width, $rules->type(new M\Type\Elementary(M\Type\Kind\ElementaryKind::Bit, '65'), 'a')?->rule);
    }

    public function testProblemRetainsTheDeclaredColumnName(): void
    {
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Temporal(M\Type\Kind\TemporalKind::Time, '7')));
        self::assertSame("Too-big precision 7 specified for 'a'. Maximum is 6.", (new FixedSizeBounds())->problem([$column])?->message());
    }

    public function testDeclarationReadsParameterTypesBeforeReturnAndBodyTypes(): void
    {
        $parameters = new M\Routine\ParameterList([new M\Routine\Parameter(new Name('p'), new M\Type\Temporal(M\Type\Kind\TemporalKind::Time, '8'))]);
        $returns = new M\Type\Temporal(M\Type\Kind\TemporalKind::DateTime, '9');
        $body = new M\Routine\Program\Block([]);
        self::assertSame("Too-big precision 8 specified for ''. Maximum is 6.", (new FixedSizeBounds())->declaration($body, $parameters, $returns)?->message());
        self::assertSame("Too-big precision 9 specified for ''. Maximum is 6.", (new FixedSizeBounds())->declaration($body, null, $returns)?->message());
        self::assertNull((new FixedSizeBounds())->declaration($body));
    }

    /**
     * @return iterable<string, array{Closure(): M\Statement, string}>
     */
    public static function providerRefusedDeclarations(): iterable
    {
        $column = static fn (): M\Table\Column\ColumnDefinition => new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Temporal(M\Type\Kind\TemporalKind::Time, '7')));
        yield 'table' => [static fn (): M\Statement => new M\Table\CreateTable(new QualifiedName(new Name('t')), [$column()]), 'A statement excludes declared type sizes invalid in every supported release.'];
        yield 'altered column' => [static fn (): M\Statement => new M\Alter\AlterTable(new QualifiedName(new Name('t')), [new M\Alter\Column\AddColumn($column())]), 'A statement excludes declared type sizes invalid in every supported release.'];
        yield 'unused branch' => [static fn (): M\Statement => new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([new M\Routine\Program\Flow\ConditionalBranch(new M\Literal\NumberLiteral('0'), [new M\Table\CreateTable(new QualifiedName(new Name('t')), [$column()])])])), 'A statement excludes declared type sizes invalid in every supported release.'];
    }

    /**
     * @return iterable<string, array{Closure(): M\Statement, string}>
     */
    public static function providerStoredDeclarations(): iterable
    {
        yield 'return' => [static fn (): M\Statement => new M\Routine\CreateFunction(new QualifiedName(new Name('f')), new M\Routine\ParameterList([]), new M\Type\Temporal(M\Type\Kind\TemporalKind::Time, '7'), new M\Routine\Definition\FunctionImplementation(new M\Routine\Program\Flow\ReturnStatement(new M\Literal\NullLiteral()))), 'Too-big precision 7 specified for \'\'. Maximum is 6.'];
        yield 'local' => [static fn (): M\Statement => new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), new M\Routine\Program\Block([new M\Routine\Program\VariableDeclaration([new Name('a')], new M\Type\Temporal(M\Type\Kind\TemporalKind::Time, '7'))])), 'Too-big precision 7 specified for \'\'. Maximum is 6.'];
        yield 'parameter' => [static fn (): M\Statement => new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([new M\Routine\Parameter(new Name('a'), new M\Type\Temporal(M\Type\Kind\TemporalKind::Time, '7'))]), new M\Routine\Program\Block([])), 'Too-big precision 7 specified for \'\'. Maximum is 6.'];
    }

    /**
     * @param Closure(): M\Statement $construct
     */
    #[DataProvider('providerStoredDeclarations')]
    public function testProblemRetainsTheFixedBoundInAStoredDefinition(Closure $construct, string $message): void
    {
        $statement = $construct();
        self::assertSame($message, (new FixedSizeBounds())->problem($statement)?->message());
        $context = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->context();
        $published = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $statement);
        self::assertSame($message, $published->warnings()[0]->message());
    }

    #[DataProvider('providerRefusedDeclarations')]
    public function testProblemPreventsAnOrdinaryDeclarationWithAFixedTypeContradiction(Closure $construct, string $message): void
    {
        $this->expectExceptionMessage($message);
        $construct();
    }
}
