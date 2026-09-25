<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\IndexAlgorithm;
use SqlSemantics\Model\Definition\IndexLock;
use SqlSemantics\Model\Statement\CreateIndexStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\MySqlTable\IndexCreations;

#[CoversClass(IndexCreations::class)]
#[Medium]
final class IndexCreationsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testWriteMovesTheIndexTypeAfterTheKeys(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind('CREATE UNIQUE INDEX ix USING HASH ON t (id) COMMENT \'c\' ALGORITHM = COPY LOCK = EXCLUSIVE');
        self::assertInstanceOf(CreateIndexStatement::class, $statement);
        self::assertSame('CREATE UNIQUE INDEX `ix` ON `t`(`id`) USING HASH COMMENT \'c\' ALGORITHM = COPY LOCK = EXCLUSIVE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testMethodIsEmptyWithoutAnIndexType(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('CREATE INDEX ix ON t (id)');
        self::assertInstanceOf(CreateIndexStatement::class, $statement);
        self::assertSame('', IndexCreations::method($statement->index->definition)->toString());
    }

    public function testPoliciesOmitDefaults(): void
    {
        self::assertSame([], IndexCreations::policies(IndexAlgorithm::Default, IndexLock::Default));
    }
}
