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
    public function testSemanticOrdersCompletedOperandsWithoutMovingOutsideWarnings(): void
    {
        $early = new IntegerLiteral('1');
        $late = new IntegerLiteral('2');
        $outside = self::createStub(Warning::class);
        $first = self::createStub(Warning::class);
        $second = self::createStub(Warning::class);
        $warnings = new Warnings(new SourceMap());
        $warnings->append($second, $late);
        $warnings->append($outside, null);
        $warnings->append($first, $early);
        $warnings->order(new IntegerLiteral('0'), [$early, $late]);
        self::assertSame([$first, $outside, $second], $warnings->all());
        self::assertSame([[$first, $early], [$second, $late]], $warnings->subjects());
    }

    public function testSubjectsRetainIdentityWithoutAnyInputLocations(): void
    {
        $subject = new IntegerLiteral('1');
        $notice = self::createStub(Warning::class);
        $other = self::createStub(Warning::class);
        $warnings = new Warnings(new SourceMap());
        $warnings->append($notice, $subject);
        $warnings->append($other, null);

        self::assertSame([[$notice, $subject]], $warnings->subjects());
        self::assertSame([$notice, $other], $warnings->all());
    }

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

    public function testInstructionsKeepTheirOwnersWhenOrderingOutsideAndUnlocatedPositions(): void
    {
        $scope = new IntegerLiteral('0');
        $early = new IntegerLiteral('1');
        $late = new IntegerLiteral('2');
        $outside = new IntegerLiteral('3');
        $one = self::createStub(Warning::class);
        $two = self::createStub(Warning::class);
        $other = self::createStub(Warning::class);
        $unlocated = self::createStub(Warning::class);
        $instruction = self::createStub(\SqlSemantics\Statement\Statement::class);
        $warnings = new Warnings(new SourceMap([new Origin($scope, 10, 50), new Origin($early, 20, 1), new Origin($late, 40, 1), new Origin($outside, 80, 1)]));
        $warnings->append($two, $late, instruction: $instruction);
        $warnings->append($unlocated, null);
        $warnings->append($other, $outside);
        $warnings->append($one, $early);

        $warnings->order($scope);

        self::assertSame([$one, $unlocated, $other, $two], $warnings->all());
        self::assertSame([[$two, $instruction]], $warnings->instructions());
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
    public function testFilteredKeepsInputNoticesAndRestoresTheOuterScope(): void
    {
        $kept = self::createStub(Warning::class);
        $omitted = self::createStub(Warning::class);
        $syntax = self::createStub(Warning::class);
        $node = new IntegerLiteral('1');
        $warnings = new Warnings(new SourceMap([], [new SourceNotice($node, $syntax, 0)]));
        $answer = $warnings->filtered(static function () use ($warnings, $kept, $omitted): int {
            $warnings->append($kept, null);
            $warnings->append($omitted, null);
            return 42;
        }, static fn (Warning $warning): bool => $warning === $kept);
        $warnings->append($omitted, null);

        self::assertSame(42, $answer);
        self::assertSame([$syntax, $kept, $omitted], $warnings->all());
    }


    public function testNoticesPreserveSpellingSubjectsAndInputBoundaries(): void
    {
        $name = new \SqlSemantics\Statement\Identifier\Name('full');
        $warning = new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::UnquotedFull);
        $warnings = new Warnings(new SourceMap(notices: [new SourceNotice($name, $warning, 16)]));
        $notice = $warnings->notices()[0];
        self::assertSame($warning, $notice->warning);
        self::assertSame($name, $notice->subject);
        self::assertEquals(new \SqlSemantics\Statement\Source\Span(16, 0), $notice->inputSource);
    }

    public function testSemanticPlacesPrefixNoticesBeforeOwnedOperandsWithoutCrossingSiblings(): void
    {
        $first = new IntegerLiteral('1');
        $operand = new IntegerLiteral('2');
        $last = new IntegerLiteral('3');
        $subject = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary(\SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator::Minus, $operand);
        $firstNotice = self::createStub(Warning::class);
        $operandNotice = self::createStub(Warning::class);
        $prefix = self::createStub(Warning::class);
        $suffix = self::createStub(Warning::class);
        $lastNotice = self::createStub(Warning::class);
        $warnings = new Warnings(new SourceMap());
        $warnings->append($suffix, $subject);
        $warnings->append($lastNotice, $last);
        $warnings->append($operandNotice, $operand);
        $warnings->append($prefix, $subject, false);
        $warnings->append($firstNotice, $first);
        $warnings->semantic([$first, $operand, $subject, $last]);
        self::assertSame([$firstNotice, $prefix, $operandNotice, $suffix, $lastNotice], $warnings->all());
    }

}
