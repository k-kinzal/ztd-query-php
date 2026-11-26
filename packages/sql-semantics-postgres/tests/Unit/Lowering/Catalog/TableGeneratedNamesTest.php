<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Lowering\Catalog\TableGeneratedNames;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeClause;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeOption;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeOptionKind;

#[CoversClass(TableGeneratedNames::class)]
#[Medium]
final class TableGeneratedNamesTest extends TestCase
{
    public function testCopiesNamesUsesTheLastApplicableLikeOption(): void
    {
        $source = new QualifiedName(new Name('items'));
        $including = new LikeOption(true, LikeOptionKind::All);
        $excludesIndexes = new LikeOption(false, LikeOptionKind::Indexes);
        $scanner = new TableGeneratedNames();

        self::assertTrue($scanner->copiesNames(new LikeClause($source, [$including, $excludesIndexes])));
    }

    public function testFromElementTracksAClauseThatCopiesNames(): void
    {
        $source = new QualifiedName(new Name('items'));
        $plain = new LikeClause($source, []);
        $copies = new LikeClause($source, [new LikeOption(true, LikeOptionKind::Identity)]);
        $scanner = new TableGeneratedNames();

        self::assertFalse($scanner->fromElement($plain));
        self::assertTrue($scanner->fromElement($copies));
    }

    public function testCopiesNamesAllowsBothFeaturesToBeExcluded(): void
    {
        $source = new QualifiedName(new Name('items'));
        $including = new LikeOption(true, LikeOptionKind::All);
        $excludesIndexes = new LikeOption(false, LikeOptionKind::Indexes);
        $excludesIdentity = new LikeOption(false, LikeOptionKind::Identity);
        $scanner = new TableGeneratedNames();
        self::assertFalse($scanner->copiesNames(new LikeClause($source, [$including, $excludesIndexes, $excludesIdentity])));
    }
}
