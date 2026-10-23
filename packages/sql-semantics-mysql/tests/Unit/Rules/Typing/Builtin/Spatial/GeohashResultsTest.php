<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Builtin\Spatial;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Spatial\GeohashResults;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(GeohashResults::class)]
#[Small]
final class GeohashResultsTest extends TestCase
{
    public function testRulesPublishAllFourNativeFunctions(): void
    {
        self::assertSame(['ST_GEOHASH', 'ST_POINTFROMGEOHASH', 'ST_LATFROMGEOHASH', 'ST_LONGFROMGEOHASH'], array_keys((new GeohashResults())->rules()));
    }

    public function testNullabilitySeparatesAnInitialDomainFromANonNullLegacyBinding(): void
    {
        $domain = Domain::string(1, Collation::known('utf8mb4_bin'));
        $settings = new Settings(Collation::known('utf8mb4_bin'), parameters: [$domain]);
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::MySql5744), [new \SqlSemantics\Statement\Identifier\Name('d')], session: $settings);
        $fact = new \SqlSemantics\Statement\Fact\ScalarFact(new \SqlSemantics\Statement\Type\Known($domain), \SqlSemantics\Statement\Type\Nullability::Nullable, new \SqlSemantics\Platform\MySql\Statement\Variable\ParameterBinding(0));
        $rules = new GeohashResults();
        self::assertSame(\SqlSemantics\Statement\Type\Nullability::NotNull, $rules->nullability('ST_LATFROMGEOHASH', [$fact], new Derivation($context), \SqlSemantics\Statement\Type\Nullability::Nullable));
        self::assertSame(\SqlSemantics\Statement\Type\Nullability::NotNull, $rules->nullability('ST_POINTFROMGEOHASH', [$fact], new Derivation($context), \SqlSemantics\Statement\Type\Nullability::Nullable));
        self::assertSame(\SqlSemantics\Statement\Type\Nullability::Nullable, $rules->nullability('ST_LATFROMGEOHASH', [$fact], new Derivation($context->withSession($settings->withParameters([$domain], [0]))), \SqlSemantics\Statement\Type\Nullability::Nullable));
    }

    public function testEncodedRetainsConnectionCollationAndChecksLengthDomain(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $call = new Invocation([Domain::null(), Domain::double()], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), $derivation);
        $domain = (new GeohashResults())->encoded($call);
        self::assertSame([100, Field::VarString, 'utf8mb4_0900_ai_ci'], [$domain->length, $domain->field, $domain->collation->name]);
        self::assertSame('Incorrect type for argument geohash max length in function st_geohash.', $derivation->facts()->diagnostics[0]->message());
    }

    /**
     * @return iterable<string, array{GrammarRelease, Domain, bool, bool}>
     */
    public static function providerParameterDomains(): iterable
    {
        yield '5.7 integer' => [GrammarRelease::MySql5744, Domain::integer(), true, false];
        yield '5.7 double' => [GrammarRelease::MySql5744, Domain::double(), true, false];
        yield '5.7 character' => [GrammarRelease::MySql5744, Domain::string(1, Collation::known('utf8mb4_bin')), false, true];
        yield '5.7 NULL' => [GrammarRelease::MySql5744, Domain::null(), true, true];
        yield '8.4 integer' => [GrammarRelease::MySql847, Domain::integer(), true, true];
        yield '8.4 double' => [GrammarRelease::MySql847, Domain::double(), true, true];
        yield '8.4 character' => [GrammarRelease::MySql847, Domain::string(1, Collation::known('utf8mb4_bin')), true, true];
        yield '8.4 binary' => [GrammarRelease::MySql847, Domain::string(1, Collation::binary()), true, false];
        yield '8.4 NULL' => [GrammarRelease::MySql847, Domain::null(), true, true];
    }

    #[DataProvider('providerParameterDomains')]
    public function testPointUsesTheBoundParameterDomainAndRelease(GrammarRelease $release, Domain $domain, bool $point, bool $coordinate): void
    {
        $derivation = new Derivation(new AnalysisContext(new LanguageProfile($release), [new \SqlSemantics\Statement\Identifier\Name('d')]));
        $call = new Invocation([$domain], [new Grouped(new Parameter())], new Settings(Collation::known('utf8mb4_bin')), $derivation);
        self::assertSame($point, (new GeohashResults())->point($call));
        self::assertCount($point ? 0 : 1, $derivation->facts()->diagnostics);
    }

    #[DataProvider('providerParameterDomains')]
    public function testCoordinateSeparatesNumericParameterRolesInLegacyReleases(GrammarRelease $release, Domain $domain, bool $point, bool $coordinate): void
    {
        $derivation = new Derivation(new AnalysisContext(new LanguageProfile($release), [new \SqlSemantics\Statement\Identifier\Name('d')]));
        $call = new Invocation([$domain], [new Grouped(new Parameter())], new Settings(Collation::known('utf8mb4_bin')), $derivation);
        self::assertSame($coordinate, (new GeohashResults())->coordinate($call, 0, 'longitude'));
        self::assertCount($coordinate ? 0 : 1, $derivation->facts()->diagnostics);
    }

    public function testOperandUnwrapsOnlyTransparentGrouping(): void
    {
        $parameter = new Parameter();
        $call = new Invocation([], [new Grouped(new Grouped($parameter))], new Settings(Collation::binary()), new Derivation((new Semantics(Dialect::MySql))->context([])));
        self::assertSame($parameter, (new GeohashResults())->operand($call, 0));
        self::assertNull((new GeohashResults())->operand($call, 1));
    }

    public function testPointDistinguishesAnUnboundPlaceholderFromAnActualStringBinding(): void
    {
        $node = new Parameter();
        $domain = Domain::string(16383, Collation::known('utf8mb4_bin'));
        $settings = new Settings(Collation::known('utf8mb4_bin'), parameters: [$domain], unboundParameters: [0]);
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::MySql5744), [new \SqlSemantics\Statement\Identifier\Name('d')], session: $settings);
        $derivation = new Derivation($context, parameters: [$node]);
        self::assertTrue((new GeohashResults())->point(new Invocation([$domain], [$node], $settings, $derivation)));
        self::assertFalse((new GeohashResults())->point(new Invocation([$domain], [$node], $settings->withParameters([$domain]), $derivation)));
    }

    public function testDecodedResolvesGeometryWithoutBorrowingSourceSyntax(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $call = new Invocation([Domain::null(), Domain::integer()], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), $derivation);
        $rules = new GeohashResults();
        self::assertSame(Field::Geometry, $rules->decoded($call, 'st_pointfromgeohash')->field);
        self::assertSame([Field::Double, 23], [$rules->decoded($call, 'ST_LATFROMGEOHASH')->field, $rules->decoded($call, 'ST_LONGFROMGEOHASH')->length]);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testArgumentRejectsBinaryHashTextIndependentlyOfItsRuntimeValue(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $call = new Invocation([Domain::string(1, Collation::binary())], [], new Settings(Collation::known('utf8mb4_0900_ai_ci')), $derivation);
        self::assertFalse((new GeohashResults())->argument($call, 0, 'ST_LATFROMGEOHASH', 'geohash', false));
        self::assertSame('Incorrect type for argument geohash in function ST_LATFROMGEOHASH.', $derivation->facts()->diagnostics[0]->message());
    }
    public function testTextDistinguishesBlobDomainsFromScalarBinaryStrings(): void
    {
        $rules = new GeohashResults();
        $binaryBlob = Domain::string(65536, Collation::binary(), Field::MediumBlob);
        $characterBlob = Domain::string(65536, Collation::known('utf8mb4_0900_ai_ci'), Field::MediumBlob);
        self::assertTrue($rules->text($binaryBlob, 'geohash'));
        self::assertTrue($rules->text($characterBlob, 'geohash'));
        self::assertTrue($rules->text($binaryBlob, 'SRID'));
        self::assertFalse($rules->text($characterBlob, 'SRID'));
    }

    public function testBinaryPreservesNullAssignmentHistoryWithoutRuntimeValues(): void
    {
        $binary = Collation::binary();
        $entry = new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry(Domain::integer(), $binary, true);
        $settings = new Settings(Collation::known('utf8mb4_0900_ai_ci'), userVariables: ['v' => $entry]);
        $node = new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('V'));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $call = new Invocation([Domain::integer()], [$node], $settings, $derivation);
        $rules = new GeohashResults();
        self::assertTrue($rules->binary($call, 0));
        self::assertFalse($rules->argument($call, 0, 'st_pointfromgeohash', 'SRID', true));
        self::assertSame('Incorrect type for argument SRID in function st_pointfromgeohash.', $derivation->facts()->diagnostics[0]->message());
    }

}
