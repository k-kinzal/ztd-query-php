<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Column\IdentityColumn;
use SqlSemantics\Schema\Column\SequenceOptions;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SequenceOptions::class)]
#[Medium]
final class SequenceOptionsTest extends TestCase
{
    public function testRetainsIntegerLiteralsIncludingNegativeOnes(): void
    {
        $start = Expression::literal(-5, Dialect::PostgreSql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $start);
        $options = new SequenceOptions($start, cycle: true);
        self::assertSame('-5', $options->start?->text);
        self::assertNull($options->increment);
        self::assertTrue($options->cycle);
    }

    public function testRejectsNonIntegerAttributes(): void
    {
        $cache = Expression::literal('ten', Dialect::PostgreSql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $cache);
        $this->expectException(InvalidStructure::class);
        new SequenceOptions(cache: $cache);
    }

    public function testBindsEveryDeclaredSequenceAttribute(): void
    {
        $column = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER GENERATED ALWAYS AS IDENTITY (START WITH 5 INCREMENT BY 2 MINVALUE 1 MAXVALUE 100 CACHE 3 CYCLE))')->tables[0]->columns[0];
        self::assertInstanceOf(IdentityColumn::class, $column->generation);
        $sequence = $column->generation->sequence;
        self::assertSame(['5', '2', '1', '100', '3'], [$sequence->start?->text, $sequence->increment?->text, $sequence->minimum?->text, $sequence->maximum?->text, $sequence->cache?->text]);
        self::assertTrue($sequence->cycle);
    }

    public function testRejectsNonIntegerStorage(): void
    {
        $this->expectException(InvalidStructure::class);
        new SequenceOptions(storage: \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'text'));
    }

    public function testRejectsOverlongSequenceNames(): void
    {
        $this->expectException(InvalidStructure::class);
        new SequenceOptions(name: new \SqlSemantics\Model\Relation\QualifiedName(['a', 'b', 'c', 'd']));
    }

    public function testRetainsNamingStoragePersistenceOwnerAndRestart(): void
    {
        $options = new SequenceOptions(storage: \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'smallint'), name: new \SqlSemantics\Model\Relation\QualifiedName(['s']), logged: true, owner: new \SqlSemantics\Model\Definition\Relation\Identity\SetSequenceOwner(null), restart: new \SqlSemantics\Model\Definition\Relation\Identity\RestartIdentity(null));
        self::assertSame('smallint', $options->storage?->name);
        self::assertSame(['s'], $options->name?->parts);
        self::assertTrue($options->logged);
        self::assertNull($options->owner?->column);
        self::assertNull($options->restart?->value);
    }
}
