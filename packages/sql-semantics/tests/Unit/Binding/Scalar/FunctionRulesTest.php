<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Ast\Tree::class)]
#[UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[UsesClass(Binder::class)]
#[UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Binding\NullFacts::class)]
#[UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[CoversClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[UsesClass(\SqlSemantics\Binding\Scope::class)]
#[UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Binding\TypeResolution::class)]
#[UsesClass(Dialect::class)]
#[UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[UsesClass(\SqlSemantics\Model\Expression::class)]
#[UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[UsesClass(\SqlSemantics\Model\Join::class)]
#[UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[UsesClass(\SqlSemantics\Model\Ordering::class)]
#[UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[UsesClass(\SqlSemantics\Model\TableUse::class)]
#[UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[UsesClass(\SqlSemantics\Schema::class)]
#[UsesClass(SchemaBuilder::class)]
#[UsesClass(\SqlSemantics\SemanticException::class)]
#[UsesClass(\SqlSemantics\Type\Nullability::class)]
#[UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
#[UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
final class FunctionRulesTest extends TestCase
{
    public function testBindSeparatesAggregateAndScalarFacts(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER, name TEXT)');
        $query = (new Binder($schema))->bind('SELECT count(*), sum(n), lower(name), custom_function(n) FROM t GROUP BY name, n');
        self::assertSame(['bigint', 'bigint', 'text', 'unknown'], array_map(static fn ($column): string => $column->expression->type->name, $query->outputs));
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $query->outputs[0]->expression->nullability);
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $query->outputs[1]->expression->nullability);
        self::assertSame('n', $query->outputs[3]->expression->lineage()[0]->column->name);
    }
    public function testTypePreservesNumericAggregateRules(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT avg(1), count(*)');
        self::assertSame('numeric', $query->outputs[0]->expression->type->name);
        self::assertSame('bigint', $query->outputs[1]->expression->type->name);
    }

    public function testNumericAggregatePromotesIntegerSum(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT sum(1), avg(1)');
        self::assertSame('bigint', $query->outputs[0]->expression->type->name);
        self::assertSame('numeric', $query->outputs[1]->expression->type->name);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerBuiltins')]
    public function testBindBuiltinResultTypes(string $sql, string $type, string $kind, string $nullable): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('select ' . $sql);
        $value = $query->outputs[0]->expression;
        self::assertSame($type, $value->type->name);
        self::assertSame($kind, $value->kind->value);
        self::assertSame($nullable, $value->nullability->value);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function providerBuiltins(): iterable
    {
        yield 'upper' => ["upper('a')", 'text', 'function', 'not-null'];
        yield 'lower null' => ['lower(NULL)', 'text', 'function', 'always-null'];
        yield 'trim' => ["trim(' a ')", 'text', 'function', 'not-null'];
        yield 'ltrim' => ["ltrim(' a')", 'text', 'function', 'not-null'];
        yield 'rtrim' => ["rtrim('a ')", 'text', 'function', 'not-null'];
        yield 'concat' => ["concat('a','b')", 'text', 'function', 'unknown'];
        yield 'concat ws' => ["concat_ws(',', 'a','b')", 'text', 'function', 'unknown'];
        yield 'substr' => ["substr('abc',1,2)", 'text', 'function', 'unknown'];
        yield 'substring' => ["substring('abc' from 1 for 2)", 'text', 'function', 'unknown'];
        yield 'replace' => ["replace('abc','a','b')", 'text', 'function', 'unknown'];
        yield 'length' => ["length('a')", 'integer', 'function', 'not-null'];
        yield 'char length' => ["char_length('a')", 'integer', 'function', 'not-null'];
        yield 'character length' => ["character_length('a')", 'integer', 'function', 'unknown'];
        yield 'abs' => ['abs(-1)', 'integer', 'function', 'not-null'];
        yield 'round' => ['round(1.5)', 'numeric', 'function', 'not-null'];
        yield 'min' => ['min(1)', 'integer', 'aggregate', 'maybe-null'];
        yield 'max' => ['max(1)', 'integer', 'aggregate', 'maybe-null'];
        yield 'avg real' => ['avg(1::real)', 'double precision', 'aggregate', 'maybe-null'];
        yield 'sum real' => ['sum(1::real)', 'real', 'aggregate', 'maybe-null'];
        yield 'sum bigint' => ['sum(1::bigint)', 'numeric', 'aggregate', 'maybe-null'];
        yield 'string agg' => ["string_agg('a',',')", 'text', 'aggregate', 'maybe-null'];
        yield 'json agg' => ['json_agg(1)', 'json', 'aggregate', 'maybe-null'];
        yield 'jsonb agg' => ['jsonb_agg(1)', 'jsonb', 'aggregate', 'maybe-null'];
        yield 'array agg' => ['array_agg(1)', 'integer[]', 'aggregate', 'maybe-null'];
        yield 'bool and' => ['bool_and(true)', 'boolean', 'aggregate', 'maybe-null'];
        yield 'bool or' => ['bool_or(true)', 'boolean', 'aggregate', 'maybe-null'];
        yield 'every' => ['every(true)', 'boolean', 'aggregate', 'maybe-null'];
        yield 'row number' => ['row_number() over ()', 'bigint', 'window', 'not-null'];
        yield 'rank' => ['rank() over ()', 'bigint', 'window', 'not-null'];
        yield 'dense rank' => ['dense_rank() over ()', 'bigint', 'window', 'not-null'];
        yield 'ntile' => ['ntile(2) over ()', 'bigint', 'window', 'not-null'];
        yield 'lag' => ['lag(1) over ()', 'integer', 'window', 'unknown'];
        yield 'lead' => ['lead(1) over ()', 'integer', 'window', 'unknown'];
        yield 'first' => ['first_value(1) over ()', 'integer', 'window', 'unknown'];
        yield 'last' => ['last_value(1) over ()', 'integer', 'window', 'unknown'];
        yield 'nth' => ['nth_value(1,2) over ()', 'integer', 'window', 'unknown'];
        yield 'percent rank' => ['percent_rank() over ()', 'double precision', 'window', 'unknown'];
        yield 'cume dist' => ['cume_dist() over ()', 'double precision', 'window', 'unknown'];
        yield 'current date' => ['current_date', 'date', 'function', 'not-null'];
        yield 'current timestamp' => ['current_timestamp', 'timestamp', 'function', 'not-null'];
        yield 'now' => ['now()', 'timestamp', 'function', 'unknown'];
    }


}
