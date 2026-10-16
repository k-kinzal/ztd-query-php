<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(LegacyDatabaseName::class)]
#[Small]
final class LegacyDatabaseNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInvalidComponents(): iterable
    {
        foreach (['empty' => '', 'period' => '.', 'parent' => '..', 'path' => 'a/b', 'backslash' => 'a\\b', 'trailing space' => 'a ', 'trailing tab' => "a\t", 'trailing newline' => "a\n", 'encoded width' => str_repeat('猫', 62), 'invalid UTF-8' => "\xff", 'NUL' => "a\0b", 'width' => str_repeat('a', 65)] as $label => $value) {
            yield $label => [$value];
        }
    }

    #[DataProvider('providerInvalidComponents')]
    public function testInvalidDirectoryComponentsAreRejected(string $value): void
    {
        $this->expectExceptionMessage('A legacy database name requires a valid directory component of at most 64 characters.');

        new LegacyDatabaseName(new Name($value));
    }

    public function testValidKeepsLegacyPunctuationAndBothWidthBoundaries(): void
    {
        self::assertTrue(LegacyDatabaseName::valid('old-name'));
        self::assertTrue(LegacyDatabaseName::valid(str_repeat('a', 64)));
        self::assertTrue(LegacyDatabaseName::valid(str_repeat('é', 64)));
        self::assertTrue(LegacyDatabaseName::valid(str_repeat('猫', 61)));
        self::assertFalse(LegacyDatabaseName::valid(str_repeat('猫', 62)));
        self::assertFalse(LegacyDatabaseName::valid(str_repeat('é', 65)));
    }

    public function testWellFormedDistinguishesDirectoryFormFromTheCharacterBound(): void
    {
        self::assertTrue(LegacyDatabaseName::wellFormed(str_repeat('a', 65)));
        self::assertFalse(LegacyDatabaseName::wellFormed(str_repeat('a', 184)));
        self::assertFalse(LegacyDatabaseName::wellFormed('a.b'));
        self::assertTrue(LegacyDatabaseName::wellFormed("a\tb"));
    }

    public function testConvertedPreservesTheDecodedOperandAndProvidesTheLookupIdentity(): void
    {
        $name = new LegacyDatabaseName(new Name('old🦄name'));
        self::assertSame('old🦄name', $name->suffix->value);
        self::assertSame('old?name', $name->value);
        self::assertSame('#mysql50#old?name', $name->identifier()->value);
        self::assertSame('old?name', LegacyDatabaseName::converted('old🦄name'));
    }

    public function testDeriveReportsConversionBeforeTheStatementDeprecation(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, '5.7.44'))->analyze('ALTER DATABASE `#mysql50#🦄` UPGRADE DATA DIRECTORY NAME');
        self::assertSame('ALTER DATABASE `#mysql50#🦄` UPGRADE DATA DIRECTORY NAME', $operation->toString());
        self::assertSame("Invalid utf8mb4 character string: '#mysql...'", $operation->facts->warnings[0]->message());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::class, $operation->facts->warnings[1]);
    }

    public function testIdentifierSuppliesTheMarkerFromTheNameRole(): void
    {
        $suffix = new Name('old-name');
        $name = new LegacyDatabaseName($suffix);

        self::assertSame($suffix, $name->suffix);
        self::assertSame('#mysql50#old-name', $name->identifier()->value);
    }

    public function testRenderQuotesTheCompleteLegacyIdentifier(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, '5.7.44'))->analyze('ALTER DATABASE `#mysql50#old-name` UPGRADE DATA DIRECTORY NAME');

        self::assertSame('ALTER DATABASE `#mysql50#old-name` UPGRADE DATA DIRECTORY NAME', $operation->toString());
    }
}
