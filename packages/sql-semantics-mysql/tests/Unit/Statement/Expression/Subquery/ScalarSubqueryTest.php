<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Expression\Tuple;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ScalarSubquery::class)]
#[Medium]
final class ScalarSubqueryTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLegacyReleases(): iterable
    {
        yield '5.6' => ['mysql-5.6.51'];
        yield '5.7' => ['mysql-5.7.44'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerLegacyReleases')]
    public function testDeriveScalarKeepsTheLegacyIntegerStorageField(string $release): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release);
        $table = $semantics->analyze('CREATE TABLE t(a INT NOT NULL)');
        $domain = (new \SqlSemantics\Platform\MySql\Rules\Typing\Precision())->domain($semantics->analyze('SELECT (SELECT t.a) FROM t', [$table])->field(0)->type);

        self::assertNotNull($domain);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Long, $domain->field);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::LongLong, $domain->storageField);
        self::assertSame(11, $domain->length);
    }

    public function testDeriveScalarKeepsTheIntegerStorageFieldOfAnUnreducedModernQuery(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(a INT NOT NULL)');
        $domain = (new \SqlSemantics\Platform\MySql\Rules\Typing\Precision())->domain($semantics->analyze('SELECT (SELECT a FROM t)', [$table])->field(0)->type);

        self::assertNotNull($domain);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Long, $domain->field);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::LongLong, $domain->storageField);
    }

    public function testDeriveScalarPreservesTheTypeAndNullabilityOfTheReducedExpression(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $subquery = (new Lowering($platform->productions($profile), new Leaves(), $profile))->expressions->expression($platform->parser($profile)->parse("SELECT (SELECT 'x')")->find('expr')[0]);
        $derivation = new Derivation($platform->context($profile, null, [], true));
        $fact = $derivation->scalar($subquery, $derivation->environment());

        self::assertInstanceOf(ScalarSubquery::class, $subquery);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertSame(['VARCHAR', Nullability::NotNull], [$fact->type->descriptor->name(), $fact->nullability]);
        self::assertNotNull($fact->replacement);
        self::assertTrue($derivation->facts()->covers($fact->replacement));
    }

    public function testDeriveScalarIsARowForSeveralColumns(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $subquery = (new Lowering($platform->productions($profile), new Leaves(), $profile))->expressions->expression($platform->parser($profile)->parse('SELECT (SELECT 1, 2)')->find('expr')[0]);
        $derivation = new Derivation($platform->context($profile, null, [], true));
        $fact = $derivation->scalar($subquery, $derivation->environment());

        self::assertInstanceOf(Known::class, $fact->type);
        self::assertEquals(new Tuple(2), $fact->type->descriptor);
    }

    public function testDeriveScalarPreservesTheNullabilityOfAConstantRowSubquery(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT (1,2) = (SELECT 1,2), (1,2) = (SELECT 1,2 WHERE 0), (1,2) = (SELECT 1,NULL)');

        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(1)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(2)->nullability);
    }

    public function testDeriveScalarFoldsTrueIntegerFiltersBeforeCombiningRowNullability(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT (1,2) = (SELECT 1,2 FROM DUAL WHERE 1), (1,2) = (SELECT 1,2 FROM DUAL WHERE 0), (1,2) = (SELECT 1,NULL FROM DUAL WHERE 1), (SELECT COUNT(*) FROM DUAL WHERE 1)');

        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(1)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(2)->nullability);
        self::assertSame(Nullability::NotNull, $operation->field(3)->nullability);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerLegacyReleases')]
    public function testDeriveScalarKeepsFilteredLegacyRowsAndAggregatesNullable(string $release): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release);
        $operation = $semantics->analyze('SELECT (1,2) = (SELECT 1,2 FROM DUAL WHERE 1), (SELECT COUNT(*) FROM DUAL WHERE 1), (SELECT COUNT(*))');

        self::assertSame(Nullability::Nullable, $operation->field(0)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(1)->nullability);
        self::assertSame(Nullability::NotNull, $operation->field(2)->nullability);
    }

    public function testRenderWritesTheQueryInParentheses(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $subquery = (new Lowering($platform->productions($profile), new Leaves(), $profile))->expressions->expression($platform->parser($profile)->parse('SELECT (select 1)')->find('expr')[0]);
        $out = new Output($platform->codec($profile));
        $subquery->render($out);

        self::assertSame('(SELECT 1)', (new Lexical())->join($out->pieces()));
    }
    public function testDeriveScalarMakesAnEmptyMySql57AggregateNullable(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $subquery = (new Lowering($platform->productions($profile), new Leaves(), $profile))->expressions->expression($platform->parser($profile)->parse('SELECT (SELECT COUNT(*) LIMIT 0)')->find('expr')[0]);
        $derivation = new Derivation($platform->context($profile, null, [], true));
        $fact = $derivation->scalar($subquery, $derivation->environment());

        self::assertSame(Nullability::Nullable, $fact->nullability);
        self::assertNull($fact->replacement);
    }

}
