<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Expressions;
use SqlSemantics\Statement\Writer;
use Tests\Contract\Composed;

#[CoversClass(Expressions::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Builder::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Queries::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\LegacyUnions::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Mode::class)]
#[Medium]
final class ExpressionsTest extends TestCase
{
    public function testLiteralsAndNegationsAreSpelledForTheLexer(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $builder = $semantics->builder();
        self::assertSame('- 5', Writer::render($builder->integer(-5)));
        self::assertSame('5', Writer::render($builder->integer(5)));
        Composed::assertExpressionRoundTrips($semantics, $builder->integer(-5));
        Composed::assertExpressionRoundTrips($semantics, $builder->float(-0.5));
    }

    public function testInfixOperatorsParenthesizeWeakerOperands(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $builder = $semantics->builder();
        self::assertSame('( a OR b ) AND c', Writer::render($builder->and($builder->or($builder->column('a'), $builder->column('b')), $builder->column('c'))));
        self::assertSame('a = b AND c = d', Writer::render($builder->and($builder->compare($builder->column('a'), '=', $builder->column('b')), $builder->compare($builder->column('c'), '=', $builder->column('d')))));
        Composed::assertExpressionRoundTrips($semantics, $builder->and($builder->or($builder->column('a'), $builder->column('b')), $builder->column('c')));
    }
}
