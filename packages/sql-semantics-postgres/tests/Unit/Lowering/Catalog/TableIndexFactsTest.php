<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Lowering\Catalog\TableIndexFacts;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Facade\StatementBinder;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeClause;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeOption;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeOptionKind;

#[CoversClass(TableIndexFacts::class)]
#[Medium]
final class TableIndexFactsTest extends TestCase
{
    public function testCollectKeepsASingleExplicitConstraintName(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([]);
        self::assertInstanceOf(AnalysisContext::class, $context);
        $binder = new StatementBinder(Platform::shared()->parser($semantics->profile()));
        $statement = $binder->resolve('CREATE TABLE items (id integer, CONSTRAINT lookup UNIQUE(id))', $context)->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        [$name, $opaque] = (new TableIndexFacts())->collect($statement);

        self::assertSame('lookup', $name?->value);
        self::assertFalse($opaque);
    }

    public function testCopiesNamesUsesTheLastApplicableLikeOption(): void
    {
        $source = new QualifiedName(new Name('items'));
        $including = new LikeOption(true, LikeOptionKind::All);
        $excludesIndexes = new LikeOption(false, LikeOptionKind::Indexes);
        $excludesIdentity = new LikeOption(false, LikeOptionKind::Identity);
        $scanner = new TableIndexFacts();

        self::assertTrue($scanner->copiesNames(new LikeClause($source, [$including, $excludesIndexes])));
        self::assertFalse($scanner->copiesNames(new LikeClause($source, [$including, $excludesIndexes, $excludesIdentity])));
    }
}
