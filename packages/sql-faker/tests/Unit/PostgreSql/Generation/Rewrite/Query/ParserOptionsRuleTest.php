<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Query\ParserOptionsRule;

#[CoversClass(ParserOptionsRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class ParserOptionsRuleTest extends TestCase
{
    #[DataProvider('providerOptions')]
    public function testRewriteRemovesOnlyOptionsForbiddenByTheirDirectOwner(string $scope, string $marker, string $option, bool $nested): void
    {
        $head = new TerminalOccurrence($marker, 10, $nested ? [0, 2] : [0], $nested ? [$scope, 'nested'] : [$scope]);
        $value = new TerminalOccurrence('OPTION', 11, [0, 1], [$scope, $option]);
        $input = new TerminalSequence([$head, $value], [$head, $value], [], [
            new ProductionOccurrence(0, null, $scope, 0), new ProductionOccurrence(1, 0, $option, 0),
        ]);
        $rule = new ParserOptionsRule();
        $result = $rule->rewrite($input);
        self::assertSame($nested ? [$head, $value] : [$head], $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{string, string, string, bool}>
     */
    public static function providerOptions(): iterable
    {
        yield ['ViewStmt', 'RECURSIVE', 'opt_check_option', false];
        yield ['ViewStmt', 'RECURSIVE', 'opt_check_option', true];
        yield ['CreateTrigStmt', 'CONSTRAINT', 'opt_or_replace', false];
        yield ['CreateTrigStmt', 'CONSTRAINT', 'opt_or_replace', true];
    }
}
