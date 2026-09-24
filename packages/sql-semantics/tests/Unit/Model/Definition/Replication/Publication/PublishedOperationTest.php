<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Replication\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Replication\Publication as Operand;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Operand\PublishedOperation::class)]
#[Medium]
final class PublishedOperationTest extends TestCase
{
    #[TestWith([Operand\PublishedOperation::Insert])]
    #[TestWith([Operand\PublishedOperation::Update])]
    #[TestWith([Operand\PublishedOperation::Delete])]
    #[TestWith([Operand\PublishedOperation::Truncate])]
    public function testEachOperationSurvivesBindingAndSerialization(Operand\PublishedOperation $operation): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind("CREATE PUBLICATION p WITH (publish = ' " . strtoupper($operation->value) . "')");
        self::assertInstanceOf(Statement\CreatePublicationStatement::class, $statement);
        self::assertSame([$operation], $statement->options->publish);
        self::assertSame("CREATE PUBLICATION \"p\" WITH (publish = '" . $operation->value . "')", $statement->toString());
    }
}
