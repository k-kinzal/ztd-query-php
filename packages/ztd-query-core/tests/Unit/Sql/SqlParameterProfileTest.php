<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSqlLexerProfiles;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Sql\LexicalDelimiters;
use ZtdQuery\Sql\LexicalPattern;
use ZtdQuery\Sql\SqlParameterProfile;

#[CoversClass(SqlParameterProfile::class)]
#[UsesClass(LexicalDelimiters::class)]
#[UsesClass(LexicalPattern::class)]
final class SqlParameterProfileTest extends TestCase
{
    public function testRefusesAPatternThatCannotBeRead(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::parameters(positionalParameterPatterns: ['/[/']);
    }

    public function testPositionalParameterLengthAtMeasuresTheFirstPatternThatMatches(): void
    {
        $profile = FakeSqlLexerProfiles::parameters(positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?/']);

        self::assertSame([3, 1, 0], [
            $profile->positionalParameterLengthAt('$12,', 0),
            $profile->positionalParameterLengthAt('?,', 0),
            $profile->positionalParameterLengthAt('a', 0),
        ]);
    }

    public function testNamedParameterPrefixAtAnswersThePrefixAPlaceholderIsWrittenWith(): void
    {
        $profile = FakeSqlLexerProfiles::parameters(namedParameterSeparators: [':' => []]);

        self::assertSame([':', null], [$profile->namedParameterPrefixAt(':a', 0), $profile->namedParameterPrefixAt('a', 0)]);
    }

    public function testNamedParameterPrefixAtAnswersNothingAfterWhatTheDialectForbids(): void
    {
        $profile = FakeSqlLexerProfiles::parameters(
            namedParameterSeparators: [':' => []],
            namedParameterForbiddenPredecessors: [':' => [':']],
        );

        self::assertNull($profile->namedParameterPrefixAt('a::b', 2));
    }

    public function testParameterNameSeparatorAtAnswersWhatJoinsAPrefixToItsName(): void
    {
        $profile = FakeSqlLexerProfiles::parameters(namedParameterSeparators: [':' => ['::']]);

        self::assertSame(['::', null], [
            $profile->parameterNameSeparatorAt(':', ':a::b', 2),
            $profile->parameterNameSeparatorAt(':', ':ab', 2),
        ]);
    }

    public function testParameterSuffixLengthMeasuresWhatAPlaceholderCarriesAfterItsName(): void
    {
        $profile = FakeSqlLexerProfiles::parameters(
            namedParameterSeparators: [':' => []],
            namedParameterSuffixPatterns: [':' => '/^\([^)]*\)/'],
        );

        self::assertSame([3, 0], [
            $profile->parameterSuffixLength(':', ':a(1)', 2),
            $profile->parameterSuffixLength(':', ':a', 2),
        ]);
    }
}
