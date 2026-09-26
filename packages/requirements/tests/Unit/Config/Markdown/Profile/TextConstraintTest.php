<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Profile\TextConstraint;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

#[CoversClass(TextConstraint::class)]
#[UsesClass(Fields::class)]
#[Small]
final class TextConstraintTest extends TestCase
{
    /**
     * @param array<string, mixed> $schema
     */
    #[DataProvider('providerTexts')]
    public function testMatchesEvaluatesConstraints(string $text, array $schema, bool $expected): void
    {
        self::assertSame($expected, TextConstraint::matches($text, $schema));
    }

    /**
     * @return array<string, array{string, array<string, mixed>, bool}>
     */
    public static function providerTexts(): array
    {
        return [
            'no constraint' => ['anything', [], true],
            'pattern matches' => ['SPEC-001', ['pattern' => '^[A-Za-z][A-Za-z0-9_.-]*$'], true],
            'pattern does not match' => ['1SPEC', ['pattern' => '^[A-Za-z][A-Za-z0-9_.-]*$'], false],
            'unanchored pattern matches inside' => ['abc', ['pattern' => 'b'], true],
            'pattern with tilde' => ['a~b', ['pattern' => '^a~b$'], true],
            'pattern with tilde does not match' => ['ab', ['pattern' => '^a~b$'], false],
            'pattern in unicode mode' => ["\u{e9}", ['pattern' => '^.$'], true],
            'const equal' => ['origin', ['const' => 'origin'], true],
            'const different' => ['Origin', ['const' => 'origin'], false],
            'enum contains' => ['b', ['enum' => ['a', 'b']], true],
            'enum lacks' => ['c', ['enum' => ['a', 'b']], false],
            'null const ignored' => ['x', ['const' => null], true],
            'null enum ignored' => ['x', ['enum' => null], true],
            'pattern matches but const differs' => ['abc', ['pattern' => 'a', 'const' => 'abd'], false],
            'pattern matches but enum lacks' => ['abc', ['pattern' => 'a', 'enum' => ['abd']], false],
            'const matches but enum lacks' => ['abc', ['const' => 'abc', 'enum' => ['abd']], false],
            'every constraint holds' => ['abc', ['pattern' => '^a', 'const' => 'abc', 'enum' => ['abc']], true],
        ];
    }

    /**
     * @param array<string, mixed> $schema
     */
    #[DataProvider('providerMalformed')]
    public function testMatchesRejectsMalformedConstraint(array $schema, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        TextConstraint::matches('text', $schema);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function providerMalformed(): array
    {
        return [
            'unknown key' => [['format' => 'id'], "text constraint: unknown field 'format'."],
            'pattern not a string' => [['pattern' => 1], 'pattern must be a nonempty string.'],
            'blank pattern' => [['pattern' => ' '], 'pattern must be a nonempty string.'],
            'enum not a list' => [['enum' => 'a'], 'enum must be a list.'],
            'enum with non-string' => [['enum' => [1]], 'enum must contain nonempty strings.'],
        ];
    }
}
