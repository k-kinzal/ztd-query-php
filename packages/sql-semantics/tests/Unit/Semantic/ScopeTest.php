<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Semantic\Name;
use SqlSemantics\Semantic\QualifiedName;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Scope::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ScopeTest extends TestCase
{
    public function testColumnAndResolveKeepCandidateAndKnownOwnershipSeparate(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite);
        self::assertSame('integer', $statement->scope->column(new Name('foo'))->type->name);
        self::assertInstanceOf(\SqlSemantics\Semantic\Reference\ResolvedColumn::class, $statement->scope->resolve(new Name('foo'), null));
    }

    public function testMatchesHidesTheOriginalNameBehindAnAlias(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT b.foo FROM bar b');
        self::assertTrue($statement->scope->matches($statement->tables[0], new QualifiedName(new Name('b'))));
        self::assertFalse($statement->scope->matches($statement->tables[0], new QualifiedName(new Name('bar'))));
    }
    public function testResolveReportsMissingInAClosedCatalog(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite);
        self::assertInstanceOf(\SqlSemantics\Semantic\Reference\MissingColumn::class, $statement->scope->resolve(new Name('missing'), null));
    }
}
