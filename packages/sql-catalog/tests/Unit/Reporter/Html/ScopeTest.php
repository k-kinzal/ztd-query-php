<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Reporter\Html\Scope;

#[CoversClass(Scope::class)]
final class ScopeTest extends TestCase
{
    /**
     * @return list<array{string, string, string|null, string}>
     */
    public static function providerOf(): array
    {
        return [
            ['App\\Repo\\Users::find', 'App\\Repo', 'App\\Repo\\Users', 'find'],
            ['\\App\\Repo\\Users::find', 'App\\Repo', 'App\\Repo\\Users', 'find'],
            ['Users::find', '', 'Users', 'find'],
            ['App\\helper', 'App', null, 'helper'],
            ['wp_insert_post', '', null, 'wp_insert_post'],
            ['{main}', '', null, '{main}'],
        ];
    }

    #[DataProvider('providerOf')]
    public function testOfReadsTheNamespaceClassAndMember(string $function, string $namespace, ?string $class, string $member): void
    {
        $scope = Scope::of($function);

        self::assertSame([$namespace, $class, $member], [$scope->namespace, $scope->class, $scope->member]);
    }

    public function testIsMainOnlyForTopLevelCode(): void
    {
        self::assertTrue(Scope::of('{main}')->isMain());
        self::assertFalse(Scope::of('f')->isMain());
    }

    public function testIsMethodWhenThereIsAClass(): void
    {
        self::assertTrue(Scope::of('A::b')->isMethod());
        self::assertFalse(Scope::of('b')->isMethod());
    }

    public function testClassShortDropsTheNamespace(): void
    {
        self::assertSame('Users', Scope::of('App\\Users::find')->classShort());
        self::assertSame('Users', Scope::of('Users::find')->classShort());
        self::assertNull(Scope::of('find')->classShort());
    }

    public function testFunctionWritesTheNameTheCatalogUses(): void
    {
        self::assertSame('App\\Users::find', Scope::of('\\App\\Users::find')->function());
        self::assertSame('App\\helper', Scope::of('App\\helper')->function());
        self::assertSame('helper', Scope::of('helper')->function());
    }

    public function testDisplayDropsTheNamespaceAndNamesTopLevelCode(): void
    {
        self::assertSame('Users::find', Scope::of('App\\Users::find')->display());
        self::assertSame('helper', Scope::of('App\\helper')->display());
        self::assertSame('top-level code', Scope::of('{main}')->display());
    }

    public function testNamespaceLabelNamesTheGlobalNamespace(): void
    {
        self::assertSame('(global namespace)', Scope::of('f')->namespaceLabel());
        self::assertSame('App', Scope::of('App\\f')->namespaceLabel());
    }
}
