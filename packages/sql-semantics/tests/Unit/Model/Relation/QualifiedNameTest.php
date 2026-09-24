<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Configuration\ReadPragmaStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(QualifiedName::class)]
#[Medium]
final class QualifiedNameTest extends TestCase
{
    public function testKeepsTheIdentifierPartsInOrder(): void
    {
        self::assertSame(['main', 'cache_size'], (new QualifiedName(['main', 'cache_size']))->parts);
    }

    public function testBindsTheWrittenQualification(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('PRAGMA main.cache_size');
        self::assertInstanceOf(ReadPragmaStatement::class, $statement);
        self::assertSame(['main', 'cache_size'], $statement->name->parts);
        self::assertSame('PRAGMA "main"."cache_size"', $statement->toString());
    }

    public function testRejectsAnEmptyPath(): void
    {
        $this->expectException(InvalidStructure::class);
        new QualifiedName([]);
    }
}
