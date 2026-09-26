<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\FunctionScope;

#[CoversClass(FunctionScope::class)]
final class FunctionScopeTest extends TestCase
{
    public function testEnterMovesIntoTheFollowedFunction(): void
    {
        $scope = (new FunctionScope('a.php'))->enter('App\\R::find', 'App\\R', 'b.php');
        self::assertSame('b.php', $scope->file);
        self::assertSame('App\\R::find', $scope->function);
        self::assertSame('App\\R', $scope->className);
    }

    public function testEnterKeepsTheCurrentFileWhenTheCalleeNamesNone(): void
    {
        self::assertSame('a.php', (new FunctionScope('a.php'))->enter('f', null, '')->file);
    }

    public function testDepthCountsTheCallsFollowedFromTheBodyTheWalkStartedIn(): void
    {
        $scope = new FunctionScope('a.php', 'root', null, ['root']);

        self::assertSame(0, $scope->depth());
        self::assertSame(2, $scope->enter('f', null, '')->enter('g', null, '')->depth());
    }

    public function testDepthIsZeroBeforeAnythingIsFollowed(): void
    {
        self::assertSame(0, (new FunctionScope('a.php'))->depth());
    }

    public function testIsFollowingDetectsRecursion(): void
    {
        $scope = (new FunctionScope('a.php'))->enter('f', null, '');
        self::assertTrue($scope->isFollowing('f'));
        self::assertFalse($scope->isFollowing('g'));
    }

    public function testTopLevelCodeIsNamedForIt(): void
    {
        self::assertSame(FunctionScope::MAIN, (new FunctionScope('a.php'))->function);
    }
}
