<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Warnings;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Statement\Fact\Warning;
use SqlSemantics\Statement\Source\Origin;
use SqlSemantics\Statement\Source\SourceMap;
use SqlSemantics\Statement\Source\SourceNotice;

#[CoversClass(Warnings::class)]
#[Small]
final class WarningsTest extends TestCase
{
    public function testAppendPreservesUnlocatedDerivationOrder(): void
    {
        $first = self::createStub(Warning::class);
        $second = self::createStub(Warning::class);
        $warnings = new Warnings(new SourceMap());
        $warnings->append($first, null);
        $warnings->append($second, new IntegerLiteral('1'));
        $warnings->order(new IntegerLiteral('2'));

        self::assertSame([$first, $second], $warnings->all());
    }

    public function testOrderPreservesOutsideAndUnlocatedPositions(): void
    {
        $scope = new IntegerLiteral('0');
        $early = new IntegerLiteral('1');
        $late = new IntegerLiteral('2');
        $outside = new IntegerLiteral('3');
        $one = self::createStub(Warning::class);
        $two = self::createStub(Warning::class);
        $other = self::createStub(Warning::class);
        $unlocated = self::createStub(Warning::class);
        $warnings = new Warnings(new SourceMap([new Origin($scope, 10, 50), new Origin($early, 20, 1), new Origin($late, 40, 1), new Origin($outside, 80, 1)]));
        $warnings->append($two, $late);
        $warnings->append($unlocated, null);
        $warnings->append($other, $outside);
        $warnings->append($one, $early);

        $warnings->order($scope);

        self::assertSame([$one, $unlocated, $other, $two], $warnings->all());
    }

    public function testOrderKeepsTiesStableAndHonorsPrefixBoundaries(): void
    {
        $scope = new IntegerLiteral('0');
        $value = new IntegerLiteral('1');
        $before = self::createStub(Warning::class);
        $after = self::createStub(Warning::class);
        $tie = self::createStub(Warning::class);
        $warnings = new Warnings(new SourceMap([new Origin($scope, 0, 10), new Origin($value, 2, 4)]));
        $warnings->append($after, $value);
        $warnings->append($tie, $value);
        $warnings->append($before, $value, false);
        $warnings->order($scope);

        self::assertSame([$before, $after, $tie], $warnings->all());
    }

    public function testAllMergesSpellingNoticesAndRetainsUnlocatedWarnings(): void
    {
        $node = new IntegerLiteral('1');
        $notice = self::createStub(Warning::class);
        $before = self::createStub(Warning::class);
        $after = self::createStub(Warning::class);
        $unlocated = self::createStub(Warning::class);
        $warnings = new Warnings(new SourceMap([new Origin($node, 10, 20)], [new SourceNotice($node, $notice, 20)]));
        $warnings->append($after, $node);
        $warnings->append($unlocated, null);
        $warnings->append($before, $node, false);

        self::assertSame([$before, $notice, $after, $unlocated], $warnings->all());
    }
}
