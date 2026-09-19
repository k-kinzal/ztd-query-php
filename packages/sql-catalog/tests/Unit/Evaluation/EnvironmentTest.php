<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(Environment::class)]
#[UsesClass(Domain::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class EnvironmentTest extends TestCase
{
    public function testReadFallsBackToAnUnresolvedValue(): void
    {
        self::assertSame('mixed', (new Environment())->read('missing')->type()->display());
    }

    public function testWriteBindsAVariable(): void
    {
        $environment = new Environment();
        $environment->write('sql', Domain::literal('SELECT 1'));
        self::assertSame('SELECT 1', $environment->read('sql')->soleLiteral()?->value);
    }

    public function testHasReportsWhetherAVariableIsBound(): void
    {
        $environment = new Environment(['sql' => Domain::literal('x')]);
        self::assertTrue($environment->has('sql'));
        self::assertFalse($environment->has('other'));
    }

    public function testForgetDropsABinding(): void
    {
        $environment = new Environment(['sql' => Domain::literal('x')]);
        $environment->forget('sql');
        self::assertFalse($environment->has('sql'));
    }

    public function testNamesListsTheBoundVariables(): void
    {
        self::assertSame(['a', 'b'], (new Environment([
            'a' => Domain::literal(1),
            'b' => Domain::literal(2),
        ]))->names());
    }

    public function testCopyIsIndependentOfTheOriginal(): void
    {
        $environment = new Environment(['a' => Domain::literal(1)]);
        $copy = $environment->copy();
        $copy->write('a', Domain::literal(2));
        self::assertSame(1, $environment->read('a')->soleLiteral()?->value);
    }

    public function testJoinKeepsWhatEitherSideMayHold(): void
    {
        $left = new Environment(['a' => Domain::literal(1)]);
        $right = new Environment(['a' => Domain::literal(2), 'b' => Domain::literal(3)]);
        $joined = $left->join($right);
        self::assertCount(2, $joined->read('a')->terms);
        self::assertSame(3, $joined->read('b')->soleLiteral()?->value);
    }

    public function testJoinKeepsABindingOnlyOneSideHas(): void
    {
        $joined = (new Environment(['a' => Domain::literal(1)]))->join(new Environment());
        self::assertSame(1, $joined->read('a')->soleLiteral()?->value);
    }

    public function testEqualsComparesBindings(): void
    {
        $left = new Environment(['a' => Domain::literal(1)]);
        self::assertTrue($left->equals(new Environment(['a' => Domain::literal(1)])));
        self::assertFalse($left->equals(new Environment(['a' => Domain::literal(2)])));
        self::assertFalse($left->equals(new Environment()));
    }
}
