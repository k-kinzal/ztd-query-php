<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(LiteralTerm::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class LiteralTermTest extends TestCase
{
    public function testToPatternResolvesFully(): void
    {
        self::assertSame('users', (new LiteralTerm('users'))->toPattern()->text());
    }

    #[DataProvider('providerToText')]
    public function testToText(string|int|float|bool|null $value, string $expected): void
    {
        self::assertSame($expected, (new LiteralTerm($value))->toText());
    }

    /**
     * @return list<array{string|int|float|bool|null, string}>
     */
    public static function providerToText(): array
    {
        return [
            ['users', 'users'],
            [7, '7'],
            [1.5, '1.5'],
            [true, '1'],
            [false, ''],
            [null, ''],
        ];
    }

    #[DataProvider('providerType')]
    public function testType(string|int|float|bool|null $value, string $expected): void
    {
        self::assertSame($expected, (new LiteralTerm($value))->type()->display());
    }

    /**
     * @return list<array{string|int|float|bool|null, string}>
     */
    public static function providerType(): array
    {
        return [
            ['users', 'string'],
            [7, 'int'],
            [1.5, 'float'],
            [true, 'bool'],
            [null, 'null'],
        ];
    }

    public function testSignatureKeepsNumbersAndTheirStringsApart(): void
    {
        self::assertNotSame((new LiteralTerm(1))->signature(), (new LiteralTerm('1'))->signature());
    }
}
