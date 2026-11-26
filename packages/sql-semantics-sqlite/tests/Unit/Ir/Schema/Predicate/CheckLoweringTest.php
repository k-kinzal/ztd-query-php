<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\CheckLowering;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable as ParsedCreateTable;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(CheckLowering::class)]
#[Small]
final class CheckLoweringTest extends TestCase
{
    public function testLowerResolvesQualifiedColumnsAndAppliesNamesInDeclarationOrder(): void
    {
        $value = (new Semantics())->analyze(
            'CREATE TABLE t (a INTEGER CONSTRAINT first CHECK (t.b > 0) CHECK (b < 7), b INTEGER, CONSTRAINT next CHECK (a = b))'
        );

        self::assertInstanceOf(CreateTable::class, $value);
        self::assertSame(['first', 'first', 'next'], array_map(static function ($check): string {
            self::assertInstanceOf(Identifier::class, $check->label);

            return $check->label->value;
        }, $value->checks));
        self::assertSame([0, 0, null], array_column($value->checks, 'column'));
        $first = $value->checks[0]->condition;
        self::assertInstanceOf(Comparison::class, $first);
        self::assertInstanceOf(ColumnReference::class, $first->left);
        self::assertSame(1, $first->left->slot);
    }

    public function testLowerRetainsOnlyTheRegistrationsOfADiscardedSubquery(): void
    {
        $value = (new Semantics())->analyze(
            'CREATE TABLE t (a INTEGER, CONSTRAINT dormant CHECK ((SELECT ?02 + ?1) NOT IN ()))'
        );

        self::assertInstanceOf(CreateTable::class, $value);
        $folded = $value->checks[0]->condition;
        self::assertInstanceOf(FoldedMembership::class, $folded);
        self::assertTrue($folded->matches);
        self::assertSame(['02', '1'], array_column($folded->bindings, 'label'));
    }

    public function testCollectAppliesTheLatestNameToEachFollowingClause(): void
    {
        $operation = (new Inspection())->inspect(
            'CREATE TABLE t (a CONSTRAINT first CHECK (a > 0) CONSTRAINT second CHECK (a < 5))'
        );
        self::assertInstanceOf(ParsedCreateTable::class, $operation->statement);
        $binding = new SourceBinding($operation->binding(), $operation->statement, [new Identifier('a')]);

        $checks = (new CheckLowering())->collect($binding, $operation->statement->columns[0]->constraints, 0);

        self::assertSame(['first', 'second'], array_map(static function ($check): string {
            self::assertInstanceOf(Identifier::class, $check->label);

            return $check->label->value;
        }, $checks));
    }
}
