<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation\Correspondence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Query as Q;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Validation\Correspondence as V;
use SqlSemantics\Statement\Validation\Failure\InvariantViolation;

#[CoversClass(V\QueryMatch::class)]
#[Small]
final class QueryMatchTest extends TestCase
{
    #[DataProvider('providerDifferentSelects')]
    public function testSelectRejectsMissingOrChangedActualClauses(C\Query\SelectDefinition $input, C\Query\SelectDefinition $different): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $actual = new Q\Select($catalog, $different);
        $this->expectException(InvariantViolation::class);
        (new V\QueryMatch())->select($catalog, $input, $actual);
    }

    /**
     * @return array<string, array{C\Query\SelectDefinition, C\Query\SelectDefinition}>
     */
    public static function providerDifferentSelects(): array
    {
        $one = new E\SqliteInteger(new \SqlSemantics\Statement\Literal\UnsignedInteger('1'));
        $two = new E\SqliteInteger(new \SqlSemantics\Statement\Literal\UnsignedInteger('2'));
        $projection = new C\Query\ProjectionDefinition(new C\Query\FieldDefinition($one), new C\Query\FieldDefinition($two));
        $plain = new C\Query\SelectDefinition($projection);
        return [
            'missing where' => [new C\Query\SelectDefinition($projection, where: $one), $plain],
            'invented where' => [$plain, new C\Query\SelectDefinition($projection, where: $one)],
            'changed where' => [new C\Query\SelectDefinition($projection, where: $one), new C\Query\SelectDefinition($projection, where: $two)],
            'quantifier' => [$plain, new C\Query\SelectDefinition($projection, quantifier: Q\Quantifier::Distinct)],
            'omitted field' => [$plain, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition($one)))],
            'reordered projection' => [$plain, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition($two), new C\Query\FieldDefinition($one)))],
            'invented alias' => [$plain, new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition($one, new Name('extra')), new C\Query\FieldDefinition($two)))],
            'missing input' => [new C\Query\SelectDefinition($projection, new C\Query\Inputs(new C\Query\NamedInput(new QualifiedName(new Name('items'))))), $plain],
            'missing limit' => [new C\Query\SelectDefinition($projection, limit: new C\Query\LimitDefinition($one)), $plain],
            'changed limit' => [new C\Query\SelectDefinition($projection, limit: new C\Query\LimitDefinition($one)), new C\Query\SelectDefinition($projection, limit: new C\Query\LimitDefinition($two))],
            'changed offset' => [new C\Query\SelectDefinition($projection, limit: new C\Query\LimitDefinition($one, $two)), new C\Query\SelectDefinition($projection, limit: new C\Query\LimitDefinition($two, $one))],
        ];
    }

    public function testSelectAcceptsFreshAliasUsesAndCorrelatedQueryOperands(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $name = new Name('answer');
        $subquery = new C\Subquery\ExistsInput(new C\Query\RowsDefinition(new C\Query\RowDefinition(new C\Expression\ColumnUse($name))));
        $input = new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition(new E\NullConstant(), $name)), where: $subquery);
        $actual = new Q\Select($catalog, $input);
        (new V\QueryMatch())->select($catalog, $input, $actual);
        self::assertSame($catalog, $actual->context());
    }

    public function testRowsRejectsLostTuplesDespiteMatchingFirstRow(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $row = new C\Query\RowDefinition(new E\NullConstant());
        $actual = new Q\Rows($catalog, new C\Query\RowsDefinition($row));
        $this->expectException(InvariantViolation::class);
        (new V\QueryMatch())->rows($catalog, new C\Query\RowsDefinition($row, $row), $actual);
    }

    public function testRowsRejectsAnOmittedPositionInAnInconsistentWidthTuple(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $value = new E\NullConstant();
        $row = new C\Query\RowDefinition($value);
        $actual = new Q\Rows($catalog, new C\Query\RowsDefinition($row, $row));
        $this->expectException(InvariantViolation::class);
        (new V\QueryMatch())->rows($catalog, new C\Query\RowsDefinition($row, new C\Query\RowDefinition($value, $value)), $actual);
    }

    public function testLimitRejectsUsingAnOuterLexicalScope(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $value = new E\NullConstant();
        $actual = new Q\SqliteLimit(new Scope(new Scope($catalog)), $value);
        $this->expectException(InvariantViolation::class);
        (new V\QueryMatch())->limit($catalog, new C\Query\LimitDefinition($value), $actual);
    }
}
