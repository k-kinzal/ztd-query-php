<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Catalog\PublishedTables::class)]
#[Medium]
final class PublishedTablesTest extends TestCase
{
    public function testDeriveSeesTheTableInTheFilter(): void
    {
        $t = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172), [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('b'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4)], [], true);
        self::assertSame([], (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('CREATE PUBLICATION p FOR TABLE t WHERE (t.a > b)', [$t])->facts->diagnostics);
    }

    public function testDeriveReportsAMissingTable(): void
    {
        self::assertSame('Relation u does not exist.', (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('CREATE PUBLICATION p FOR TABLE u', [])->facts->diagnostics[0]->message());
    }

    public function testColumnsReportsAMissingColumn(): void
    {
        $t = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172), [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4)], [], true);
        self::assertSame('column "z" of relation "t" does not exist', (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('CREATE PUBLICATION p FOR TABLE t (z)', [$t])->facts->diagnostics[0]->message());
    }

    public function testColumnsReportsARepeatedColumn(): void
    {
        $t = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172), [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4)], [], true);
        self::assertSame('duplicate column "a" in publication column list', (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('CREATE PUBLICATION p FOR TABLE t (a, a)', [$t])->facts->diagnostics[0]->message());
    }

    public function testColumnsAcceptsAnyColumnOfAnIncompleteTable(): void
    {
        $t = new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('t')), new \SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172), [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin::Int4)], [], false);
        self::assertSame([], (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('CREATE PUBLICATION p FOR TABLE t (z)', [$t])->facts->diagnostics);
    }
}
