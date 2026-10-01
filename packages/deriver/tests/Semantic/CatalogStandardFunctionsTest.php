<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Query\ReturnQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

/**
 * Verifies catalog integration through the public analysis and model contracts.
 */
#[CoversNothing]
#[Small]
final class CatalogStandardFunctionsTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    #[DataProvider('expressions')]
    public function testStandardConstruction(string $expression, mixed $expected): void
    {
        $result = Analysis::returns('<?php function target(){return ' . $expression . ';}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        foreach ($result->normalOutcomes as $outcome) {
            self::assertSame($expected, $outcome->values['return']->native());
        }
    }

    /**
     * @return list<array{string, mixed}> Independent source fixtures and expected observations
     */
    public static function expressions(): array
    {
        return [
            ['array_fill(-2,3,"?")', [-2 => '?',-1 => '?',0 => '?']],
            ['implode(",",array_fill(0,3,"?"))', '?,?,?'],
            ['str_repeat("?,",3)', '?,?,?,'],
            ['vsprintf("%s %d",["id",7])', 'id 7'],
            ['strval(42)', '42'],
            ['intval("0xff",0)', 255],
            ['ucfirst("users")', 'Users'],
            ['lcfirst("Users")', 'users'],
            ['ltrim("  users  ")', 'users  '],
            ['rtrim("  users  ")', '  users'],
        ];
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testImplodeRetainsBothKnownFragmentsAroundAMixedElement(): void
    {
        $result = Analysis::returns('<?php function target($x){return implode("",["SELECT ",$x," FROM users"]);}');
        $json = json_encode($result->normalOutcomes[0]->values, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('SELECT ', $json);
        self::assertStringContainsString(' FROM users', $json);
        self::assertNotEmpty($result->frontiers);
        self::assertNotEmpty($result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testBackedEnumParametersEnumerateCasesAndConstantExpressions(): void
    {
        $result = Analysis::returns('<?php enum Status:string {case A="a";case B="b"."c";} function target(Status $status){return $status->value;}');
        self::assertEqualsCanonicalizing(['a','bc'], array_map(static fn (\Deriver\Result\Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes));
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testRuntimeDefineIsVisibleThroughNamespaceFallback(): void
    {
        $session = Analysis::session('<?php namespace App; function target(){define("TABLE","users");return TABLE;}');
        $result = $session->derive(new ReturnQuery('App\\target'));
        self::assertSame('users', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testNamespaceConstantsShadowGlobalConstants(): void
    {
        $session = Analysis::session('<?php namespace {const TABLE="global";} namespace App {const TABLE="local";function target(){return TABLE;}}');
        $result = $session->derive(new ReturnQuery('App\\target'));
        self::assertSame('local', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
}
