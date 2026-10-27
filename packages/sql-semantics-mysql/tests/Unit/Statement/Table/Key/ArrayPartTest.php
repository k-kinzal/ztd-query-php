<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ArrayPart;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(ArrayPart::class)]
#[Medium]
final class ArrayPartTest extends TestCase
{
    public function testDeriveKeyPartResolvesTheDocumentAgainstTheIndexedTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(j JSON)');
        $document = new ColumnUse(new Name('j'));
        $part = new ArrayPart($document, new CastTarget(CastKind::Signed));
        $index = new CreateIndex(new Name('k'), new QualifiedName(new Name('t')), [$part]);
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context([$table]), $index, new \SqlSemantics\Statement\Source\SourceMap());
        self::assertInstanceOf(CreateIndex::class, $operation);
        $bound = $operation->parts[0];
        self::assertInstanceOf(ArrayPart::class, $bound);
        $resolution = $bound->expression->scalarInformation()->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($table->declarations()[0]->columns[0], $resolution->slot->column);
        self::assertSame([], $operation->diagnostics());
    }

    public function testDeriveScalarSeparatesTheEntryDomainFromTheDocumentDomain(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(j JSON)');
        $document = new ColumnUse(new Name('j'));
        $part = new ArrayPart($document, new CastTarget(CastKind::Char, '5'));
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context([$table]), new CreateIndex(new Name('k'), new QualifiedName(new Name('t')), [$part]), new \SqlSemantics\Statement\Source\SourceMap());
        self::assertInstanceOf(CreateIndex::class, $operation);
        $bound = $operation->parts[0];
        self::assertInstanceOf(ArrayPart::class, $bound);
        $entry = $bound->scalarInformation()->type;
        $input = $bound->expression->scalarInformation()->type;
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $entry);
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Known::class, $input);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $entry->descriptor);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $input->descriptor);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Json, $input->descriptor->kind);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String, $entry->descriptor->kind);
        self::assertSame(5, $entry->descriptor->length);
        self::assertSame('utf8mb4_0900_bin', $entry->descriptor->collation->name);
    }

    public function testDeriveKeyPartPublishesOnlyFinalOccurrencesAfterLowering(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('CREATE INDEX k ON t ((CAST(j AS SIGNED ARRAY)))');
        self::assertInstanceOf(CreateIndex::class, $operation);
        self::assertInstanceOf(ArrayPart::class, $operation->parts[0]);
        self::assertSame('CREATE INDEX k ON t ((CAST(j AS SIGNED ARRAY)))', $operation->toString());
    }

    public function testRenderKeepsTheElementTypeInTheIndexPart(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $part = new ArrayPart(new ColumnUse(new Name('j')), new CastTarget(CastKind::Decimal, '10', '2'));
        $index = new CreateIndex(new Name('k'), new QualifiedName(new Name('t')), [$part]);
        $operation = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context(), $index, new \SqlSemantics\Statement\Source\SourceMap());

        self::assertSame('CREATE INDEX k ON t ((CAST(j AS DECIMAL(10, 2) ARRAY)))', $operation->toString());
    }
}
