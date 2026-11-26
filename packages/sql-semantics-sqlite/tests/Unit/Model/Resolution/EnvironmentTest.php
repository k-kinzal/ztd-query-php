<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\Sqlite\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\Sqlite\Model\Resolution\CommonBinding;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\Field;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

#[CoversClass(Environment::class)]
#[Small]
final class EnvironmentTest extends TestCase
{
    public function testOwningSchemaIsInheritedFromTheViewBoundary(): void
    {
        $schema = new Name('main');
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [$schema]);
        $root = new Environment($context);
        $view = new Environment($context, $root, relationSchema: $schema);

        self::assertNull($root->owningSchema());
        self::assertSame($schema, (new Environment($context, $view))->owningSchema());
    }

    public function testCommonTableFindsTheNearestBindingWithLaterOnesShadowingEarlierOnes(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')], [], true, Comparison::AsciiInsensitive);
        $outerBinding = new CommonBinding(new Name('c'), new Star(), new RowShape([]));
        $earlier = new CommonBinding(new Name('c'), new Star(), new RowShape([]));
        $later = new CommonBinding(new Name('C'), new Star(), new RowShape([]));
        $outer = new Environment($context, null, [], [$outerBinding]);
        $inner = new Environment($context, $outer, [], [$earlier, $later]);

        self::assertSame($later, $inner->commonTable(new Name('c')));
        self::assertSame($outerBinding, $outer->commonTable(new Name('c')));
        self::assertSame($outerBinding, (new Environment($context, $outer))->commonTable(new Name('c')));
        self::assertNull($inner->commonTable(new Name('d')));
    }

    public function testCommonTableComparesNamesAsTheContextSays(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $binding = new CommonBinding(new Name('c'), new Star(), new RowShape([]));
        $environment = new Environment($context, null, [], [$binding]);

        self::assertSame($binding, $environment->commonTable(new Name('c')));
        self::assertNull($environment->commonTable(new Name('C')));
    }

    public function testAliasedFindsEveryOutputFieldWithTheAlias(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')], [], true, Comparison::Sensitive, Comparison::AsciiInsensitive);
        $first = new Field(0, new OutputSlot(new Name('x'), new Known(Storage::Integer), Nullability::NotNull));
        $second = new Field(1, new OutputSlot(new Name('X'), new Known(Storage::Text), Nullability::Nullable));
        $unnamed = new Field(2, new OutputSlot(null, new Known(Storage::Text), Nullability::Nullable));
        $environment = new Environment($context, null, [], [], [$first, $second, $unnamed]);

        self::assertSame([$first, $second], $environment->aliased(new Name('x')));
        self::assertSame([], $environment->aliased(new Name('y')));
    }
}
