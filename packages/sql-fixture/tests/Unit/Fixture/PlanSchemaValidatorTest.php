<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\PlanSchemaException;
use SqlFixture\Fixture\PlanSchemaValidator;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\PlanParser;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;
use SqlFixture\Plan\RelationSide;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaNotFoundException;
use SqlFixture\Schema\StaticSchemaResolver;
use SqlFixture\Schema\TableSchema;

#[CoversClass(PlanSchemaValidator::class)]
#[UsesClass(PlanSchemaException::class)]
#[UsesClass(FixturePlan::class)]
#[UsesClass(PlanParser::class)]
#[UsesClass(Relation::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(RelationKind::class)]
#[UsesClass(RelationSide::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(StaticSchemaResolver::class)]
#[UsesClass(SchemaNotFoundException::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(ColumnDefinition::class)]
#[CoversClass(\SqlFixture\Fixture\Validation\EndpointValidator::class)]
#[UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[UsesClass(\SqlFixture\Schema\SchemaResolverInterface::class)]
#[UsesClass(\SqlFixture\Schema\TableIdentifier::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\RelationCursor::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\RelationReader::class)]
#[UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[UsesClass(\SqlFixture\Plan\Printing\RelationGroups::class)]
#[UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\ColumnParser::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\DefaultExpression::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\DefinitionIntegrity::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\TableDefinition::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\TypeParameters::class)]
#[UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\TableDefinitionInput::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\GeneratedColumnReferenceException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\MissingRelationValueException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\UnknownPlanColumnException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyPlanException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\MissingEndpointColumnsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\InvalidTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnbalancedBracketsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnexpectedPlanTokenException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnsupportedManyToManyException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CompositeArityMismatchException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\DuplicateColumnBindingException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CyclicDependencyException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnboundedSelfReferenceException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class)]
final class PlanSchemaValidatorTest extends TestCase
{
    #[Test]
    public function testValidateAPlanMatchingTheSchemaPasses(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $validator = new PlanSchemaValidator($schemas);

        $validator->validate(FixturePlan::from('order.id < order_detail.order_id'));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    #[DataProvider('providerMismatchedPlans')]
    public function aColumnTheTableDoesNotHaveIsRejected(string $plan, string $expected): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $validator = new PlanSchemaValidator($schemas);

        $this->expectException(PlanSchemaException::class);
        $this->expectExceptionMessage($expected);

        $validator->validate(FixturePlan::from($plan));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerMismatchedPlans(): array
    {
        return [
            'parent column misspelt' => [
                'order.idd < order_detail.order_id',
                'order has no column idd',
            ],
            'child column misspelt' => [
                'order.id < order_detail.oder_id',
                'order_detail has no column oder_id',
            ],
            'column belongs to another table' => [
                'order.quantity < order_detail.order_id',
                'order has no column quantity',
            ],
            'one of a composite key is wrong' => [
                'order.(id, nope) < order_detail.(order_id, quantity)',
                'order has no column nope',
            ],
        ];
    }

    #[Test]
    public function testATableTheResolverDoesNotKnowIsRejected(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $validator = new PlanSchemaValidator($schemas);

        $this->expectException(SchemaNotFoundException::class);
        $this->expectExceptionMessage('Schema not found for table: nope');

        $validator->validate(FixturePlan::from('nope.id < order_detail.order_id'));
    }

    #[Test]
    public function testATableNamedWithoutAnyRelationIsCheckedToo(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $validator = new PlanSchemaValidator($schemas);

        $this->expectException(SchemaNotFoundException::class);
        $this->expectExceptionMessage('Schema not found for table: nope');

        $validator->validate(FixturePlan::from('order.id < order_detail.order_id, nope'));
    }

    #[Test]
    public function testAGeneratedColumnCannotBeLinked(): void
    {
        $resolver = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', autoIncrement: true),
                'code' => new ColumnDefinition('code', 'VARCHAR', length: 10, generated: true),
            ], ['id']),
            new TableSchema('order_detail', [
                'order_code' => new ColumnDefinition('order_code', 'VARCHAR', length: 10),
            ]),
        ]);

        $this->expectException(\SqlFixture\Fixture\Exception\GeneratedColumnReferenceException::class);
        $this->expectExceptionMessage('order.code is a generated column');

        (new PlanSchemaValidator($resolver))->validate(FixturePlan::from('order.code < order_detail.order_code'));
    }

    #[Test]
    public function testAGeneratedColumnCannotBeWrittenIntoEither(): void
    {
        $resolver = new StaticSchemaResolver([
            new TableSchema('order', ['id' => new ColumnDefinition('id', 'INT', autoIncrement: true)], ['id']),
            new TableSchema('order_detail', [
                'order_id' => new ColumnDefinition('order_id', 'INT', generated: true),
            ]),
        ]);

        $this->expectException(\SqlFixture\Fixture\Exception\GeneratedColumnReferenceException::class);
        $this->expectExceptionMessage('order_detail.order_id is a generated column');

        (new PlanSchemaValidator($resolver))->validate(FixturePlan::from('order.id < order_detail.order_id'));
    }
}
