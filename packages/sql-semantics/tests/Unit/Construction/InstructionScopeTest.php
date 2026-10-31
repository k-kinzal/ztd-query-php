<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\InstructionScope;
use SqlSemantics\Statement\Node;

#[CoversClass(InstructionScope::class)]
#[Small]
final class InstructionScopeTest extends TestCase
{
    public function testCurrentIsAbsentOutsideAStoredDefinition(): void
    {
        self::assertNull((new InstructionScope())->current());
    }

    public function testWithinRestoresTheEnclosingInstruction(): void
    {
        $scope = new InstructionScope();
        $outer = self::createStub(Node::class);
        $inner = self::createStub(Node::class);
        $scope->within($outer, static function () use ($scope, $outer, $inner): void {
            self::assertSame($outer, $scope->current());
            $scope->within($inner, static function () use ($scope, $inner): void {
                self::assertSame($inner, $scope->current());
            });
            self::assertSame($outer, $scope->current());
        });
        self::assertNull($scope->current());
    }
}
