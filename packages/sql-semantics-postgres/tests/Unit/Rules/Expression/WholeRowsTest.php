<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\WholeRows;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\Composite;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\AmbiguousRelation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(WholeRows::class)]
#[Small]
final class WholeRowsTest extends TestCase
{
    public function testStarReportsAnImproperName(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = (new WholeRows())->star($derivation, $derivation->environment(), [new Name('a'), new Name('b'), new Name('c'), new Name('d')]);
        self::assertInstanceOf(Invalid::class, $fact->type);
    }

    public function testRowDependsOnAnOpenShape(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $missing = new UndeclaredRelation(new QualifiedName(new Name('t')));
        $environment = new Environment($context, null, [new VisibleRelation(self::createStub(Relation::class), new RowShape([], [$missing]), new Name('t'))]);
        self::assertEquals(new Dependent([$missing]), (new WholeRows())->row(new Derivation($context), $environment, new QualifiedName(new Name('t')))?->type);
    }

    public function testRowReportsTwoRelationsOfOneName(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $relation = new VisibleRelation(self::createStub(Relation::class), new RowShape([]), new Name('t'));
        $environment = new Environment($context, null, [$relation, new VisibleRelation(self::createStub(Relation::class), new RowShape([]), new Name('t'))]);
        $fact = (new WholeRows())->row(new Derivation($context), $environment, new QualifiedName(new Name('t')));
        self::assertInstanceOf(AmbiguousRelation::class, $fact?->type instanceof Invalid ? $fact->type->cause : null);
    }

    public function testFindSearchesTheEnclosingLevels(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $relation = new VisibleRelation(self::createStub(Relation::class), new RowShape([]), null, new QualifiedName(new Name('t'), new Name('s')));
        $environment = new Environment($context, new Environment($context, null, [$relation]));
        self::assertSame([$relation], (new WholeRows())->find($environment, new QualifiedName(new Name('t'))));
        self::assertEquals(new Known(new Composite([], new Name('t'))), (new WholeRows())->row(new Derivation($context), $environment, new QualifiedName(new Name('t'), new Name('s')))?->type);
    }
}
