<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\DefinitionWords;
use SqlSemantics\Dialect;

#[CoversClass(DefinitionWords::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DefinitionWordsTest extends TestCase
{
    public function testOfUppercasesTheClauseAndAcceptsAbsence(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('CREATE CAST (integer AS text) WITH INOUT as implicit');
        self::assertSame(['AS', 'IMPLICIT'], DefinitionWords::of(Tree::outer($tree, ['cast_context'])[0]));
        self::assertSame([], DefinitionWords::of(null));
    }
}
