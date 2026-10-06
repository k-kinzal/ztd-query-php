<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Relation\IndexChoice;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

#[CoversClass(IndexChoice::class)]
#[Medium]
final class IndexChoiceTest extends TestCase
{
    public function testRenderWritesIndexedByWithTheIndexName(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $built = new Operation($semantics->context(), new Select([new Star()], new TableInput(new QualifiedName(new Name('t')), null, new IndexChoice(new Name('i')))));
        $query = $semantics->analyze('select * from t indexed by i');

        self::assertSame('SELECT * FROM t INDEXED BY i', $built->toString());
        self::assertSame('SELECT * FROM t INDEXED BY i', $query->toString());
        self::assertInstanceOf(TableInput::class, $query->singleNamedInput());
        self::assertSame('i', $query->singleNamedInput()->index?->index?->value);
    }

    public function testRenderWritesNotIndexedWhenNoIndexIsNamed(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $built = new Operation($semantics->context(), new Delete(new MutationTarget(new QualifiedName(new Name('t')), null, new IndexChoice())));
        $query = $semantics->analyze('SELECT * FROM t NOT INDEXED');

        self::assertSame('DELETE FROM t NOT INDEXED', $built->toString());
        self::assertSame('SELECT * FROM t NOT INDEXED', $query->toString());
        self::assertInstanceOf(TableInput::class, $query->singleNamedInput());
        self::assertNotNull($query->singleNamedInput()->index);
        self::assertNull($query->singleNamedInput()->index->index);
    }
}
