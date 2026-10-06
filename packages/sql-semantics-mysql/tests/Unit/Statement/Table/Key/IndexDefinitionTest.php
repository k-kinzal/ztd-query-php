<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

#[CoversClass(IndexDefinition::class)]
#[Medium]
final class IndexDefinitionTest extends TestCase
{
    public function testDeriveElementDerivesTheKeyParts(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, UNIQUE KEY u USING HASH ((a * 2)) COMMENT \'x\')');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $index = $statement->elements[1];

        self::assertInstanceOf(IndexDefinition::class, $index);
        self::assertSame(IndexKind::Unique, $index->kind);
        self::assertTrue($index->keyword);
        self::assertSame(IndexAlgorithm::Hash, $index->algorithm);
        self::assertSame([], $create->facts->diagnostics);
    }

    public function testRenderWritesKindNameAlgorithmPartsAndOptions(): void
    {
        self::assertSame('CREATE TABLE t (a INT, CONSTRAINT pk PRIMARY KEY USING BTREE (a), SPATIAL INDEX s (a), FULLTEXT f (a) INVISIBLE)', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, CONSTRAINT pk PRIMARY KEY USING BTREE (a), SPATIAL INDEX s (a), FULLTEXT f (a) INVISIBLE)')->toString());
    }
}
