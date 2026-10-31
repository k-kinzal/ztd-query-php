<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Dml\EvaluationOperands;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(EvaluationOperands::class)]
#[Small]
final class EvaluationOperandsTest extends TestCase
{
    public function testCheckPreservesEveryGrammaticalItemInOrder(): void
    {
        $items = [new M\Query\Star(), new M\Query\SelectExpression(new M\Literal\NumberLiteral('1')), new M\Name\TableWildcard(new QualifiedName(new Name('t')))];
        self::assertSame($items, (new EvaluationOperands())->check($items));
    }

    /**
     * @return iterable<string, array{list<M\Query\SelectExpression|M\Query\Star>}>
     */
    public static function providerMisplaced(): iterable
    {
        yield 'after expression' => [[new M\Query\SelectExpression(new M\Literal\NumberLiteral('1')), new M\Query\Star()]];
        yield 'after star' => [[new M\Query\Star(), new M\Query\Star()]];
    }

    /**
     * @param list<M\Query\SelectExpression|M\Query\Star> $items
     */
    #[DataProvider('providerMisplaced')]
    public function testCheckRefusesMisplacedStars(array $items): void
    {
        $this->expectExceptionMessage('A DO item is an expression or a qualified star, and an unqualified star comes first.');
        (new EvaluationOperands())->check($items);
    }

    public function testDeriveKeepsDependenciesOfNonScalarItems(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());
        $row = new M\Expression\Row([new M\Name\ColumnUse(new Name('missing')), new M\Literal\NumberLiteral('2')]);
        (new EvaluationOperands())->derive([new M\Routine\Program\DeferredProjection($row)], $derivation, $derivation->environment());
        self::assertCount(1, $derivation->diagnostics());
        self::assertStringContainsString('missing', $derivation->diagnostics()[0]->message());
    }

    public function testProblemSeparatesExpansionFromScalarPreparation(): void
    {
        $operands = new EvaluationOperands();
        self::assertNull($operands->problem([new M\Query\SelectExpression(new M\Literal\NullLiteral())]));
        $row = new M\Routine\Program\DeferredProjection(new M\Expression\Row([new M\Literal\NullLiteral(), new M\Literal\NullLiteral()]));
        self::assertSame('Operand should contain 1 column(s), not 2.', $operands->problem([$row])?->message());
        self::assertInstanceOf(M\Dml\Problem\EvaluationWildcard::class, $operands->problem([$row, new M\Name\TableWildcard(new QualifiedName(new Name('t')))]));
    }
}
