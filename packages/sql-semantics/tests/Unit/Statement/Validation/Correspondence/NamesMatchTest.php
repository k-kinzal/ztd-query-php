<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation\Correspondence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\Validation\Correspondence as V;

#[CoversClass(V\NamesMatch::class)]
#[Small]
final class NamesMatchTest extends TestCase
{
    #[DataProvider('providerNames')]
    public function testSameKeepsAbsenceSpellingAndQuotingDistinct(?Name $expected, ?Name $actual, bool $same): void
    {
        self::assertSame($same, V\NamesMatch::same($expected, $actual));
    }

    /**
     * @return array<string, array{?Name, ?Name, bool}>
     */
    public static function providerNames(): array
    {
        return [
            'absent' => [null, null, true],
            'missing' => [new Name('id'), null, false],
            'extra' => [null, new Name('id'), false],
            'separate values' => [new Name('id'), new Name('id'), true],
            'case' => [new Name('Id'), new Name('id'), false],
            'quoted' => [new Name('id'), new Name('id', Quote::Double), false],
        ];
    }

    public function testQualifiedRejectsExchangedNamespacePositions(): void
    {
        self::assertFalse(V\NamesMatch::qualified(new QualifiedName(new Name('t'), new Name('s'), new Name('c')), new QualifiedName(new Name('t'), new Name('c'), new Name('s'))));
        self::assertFalse(V\NamesMatch::qualified(new QualifiedName(new Name('t')), null));
        self::assertTrue(V\NamesMatch::qualified(new QualifiedName(new Name('t')), new QualifiedName(new Name('t'))));
    }
}
