<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnostic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Check::class)]
#[Small]
final class CheckTest extends TestCase
{
    public function testInputKeepsAHoldingCondition(): void
    {
        $name = new QualifiedName(new Name('t'), new Name('s'), new Name('c'));

        self::assertSame('c', $name->catalog?->value);
    }

    public function testInputRejectsAFailingConditionWithTheMessageOfTheConstructor(): void
    {
        $this->expectExceptionMessage('A catalog qualifier requires a schema qualifier.');

        new QualifiedName(new Name('t'), null, new Name('c'));
    }

    public function testListOfNarrowsAListOfTheClass(): void
    {
        $names = [new Name('a'), new Name('b')];

        $list = Check::listOf($names, Name::class, 'Names only.');

        self::assertSame($names, $list);
    }

    public function testListOfRejectsAnItemOfAnotherClass(): void
    {
        $this->expectExceptionMessage('Names only.');

        Check::listOf([new Name('a'), new QualifiedName(new Name('b'))], Name::class, 'Names only.');
    }

    public function testListOfRejectsAMapInsteadOfAList(): void
    {
        $this->expectExceptionMessage('Names only.');

        Check::listOf(['first' => new Name('a')], Name::class, 'Names only.');
    }

    public function testListOfRejectsAListShorterThanTheMinimum(): void
    {
        $this->expectExceptionMessage('At least two names.');

        Check::listOf([new Name('a')], Name::class, 'At least two names.', 2);
    }

    public function testListOfAcceptsAnEmptyListWithoutMinimum(): void
    {
        self::assertSame([], Check::listOf([], Name::class, 'Names only.'));
    }

    public function testInvariantRejectsAFailingConditionWithTheMessageOfTheCheck(): void
    {
        $this->expectExceptionMessage('The production has no nonterminal at position 0: where_opt:');

        (new Form(new Node('where_opt', 0, []), 'where_opt:'))->node(0);
    }

    public function testInvariantKeepsAHoldingCondition(): void
    {
        $child = new Node('expr', 0, []);

        self::assertSame($child, (new Form(new Node('where_opt', 1, [$child]), 'where_opt: expr'))->node(0));
    }
}
