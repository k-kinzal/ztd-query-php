<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Replication\Publication as Operand;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Replication\PublicationOptionReader::class)]
#[Medium]
final class PublicationOptionReaderTest extends TestCase
{
    #[TestWith(['publish_via_partition_root', true])]
    #[TestWith(['publish_via_partition_root = OFF', false])]
    #[TestWith(["publish_via_partition_root = 'true'", true])]
    #[TestWith(['publish_via_partition_root = 0', false])]
    public function testReadAcceptsBooleanSpellings(string $option, bool $value): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p WITH (' . $option . ')');
        self::assertInstanceOf(Statement\CreatePublicationStatement::class, $statement);
        self::assertSame($value, $statement->options->viaPartitionRoot);
        self::assertNull($statement->options->publish);
    }

    #[TestWith(['binary'])]
    #[TestWith(['publish_via_partition_root, publish_via_partition_root'])]
    #[TestWith(['publish_via_partition_root = none'])]
    #[TestWith(['publish_via_partition_root = 2'])]
    #[TestWith(['publish'])]
    public function testReadRejectsAnUnknownRepeatedOrMistypedOption(string $option): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p WITH (' . $option . ')');
    }

    public function testOperationsFoldUnquotedItemsAndKeepQuotedOnes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind("CREATE PUBLICATION p WITH (publish = ' Insert ,\"update\"')");
        self::assertInstanceOf(Statement\CreatePublicationStatement::class, $statement);
        self::assertSame([Operand\PublishedOperation::Insert, Operand\PublishedOperation::Update], $statement->options->publish);
    }

    #[TestWith(["'\"UPDATE\"'"])]
    #[TestWith(["'insert,,delete'"])]
    #[TestWith(["'merge'"])]
    #[TestWith(["'x\"update\"'"])]
    #[TestWith(["'\"update\"x'"])]
    public function testOperationsRejectAnUnknownOperation(string $value): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DefinitionArgument->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p WITH (publish = ' . $value . ')');
    }

    public function testReadWithoutADefinitionSetsNothing(): void
    {
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public'));
        $options = \SqlSemantics\Binding\Statement\Definition\Replication\PublicationOptionReader::read(null, $context);
        self::assertNull($options->publish);
        self::assertNull($options->viaPartitionRoot);
    }

    public function testOperationsReadABlankListAsNoOperation(): void
    {
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public'));
        $elements = \SqlSemantics\Binding\Statement\Definition\TypeSystem\DefinitionElement::list((new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse("CREATE PUBLICATION p WITH (publish = ' ')"), $context);
        self::assertSame([], \SqlSemantics\Binding\Statement\Definition\Replication\PublicationOptionReader::operations($elements[0], $context));
    }
}
