<?php

declare(strict_types=1);

namespace Tests\Unit\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Resolution\CommonBinding;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Environment::class)]
#[Small]
final class EnvironmentTest extends TestCase
{
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
