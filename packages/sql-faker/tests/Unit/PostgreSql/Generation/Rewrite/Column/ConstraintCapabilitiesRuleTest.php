<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Column\ConstraintCapabilitiesRule;

#[CoversClass(ConstraintCapabilitiesRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class ConstraintCapabilitiesRuleTest extends TestCase
{
    #[DataProvider('providerCapabilities')]
    public function testRewriteAttributesRespectTheirOwningConstraint(string $scope, string $kind, string $option, bool $kept): void
    {
        $head = new TerminalOccurrence($kind, 10, [0], [$scope]);
        $words = explode(' ', $option);
        $attributes = array_map(static fn (int $index, string $name): TerminalOccurrence =>
            new TerminalOccurrence($name, 11 + $index, [0, 1, 2], [$scope, 'ConstraintAttributeSpec', 'ConstraintAttributeElem']), array_keys($words), $words);
        $terminals = [$head, ...$attributes];
        $input = new TerminalSequence($terminals, $terminals, [], [
            new ProductionOccurrence(0, null, $scope, 0), new ProductionOccurrence(1, 0, 'ConstraintAttributeSpec', 0),
            new ProductionOccurrence(2, 1, 'ConstraintAttributeElem', 0),
        ]);
        $rule = new ConstraintCapabilitiesRule();
        $result = $rule->rewrite($input);
        self::assertSame($kept ? $terminals : [$head], $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{string, string, string, bool}>
     */
    public static function providerCapabilities(): iterable
    {
        foreach (['CHECK', 'UNIQUE', 'PRIMARY', 'FOREIGN', 'EXCLUDE'] as $kind) {
            yield ['ConstraintElem', $kind, 'DEFERRABLE', $kind !== 'CHECK'];
            yield ['ConstraintElem', $kind, 'INITIALLY DEFERRED', $kind !== 'CHECK'];
            yield ['ConstraintElem', $kind, 'NOT VALID', in_array($kind, ['CHECK', 'FOREIGN'], true)];
            yield ['ConstraintElem', $kind, 'NO INHERIT', $kind === 'CHECK'];
            yield ['ConstraintElem', $kind, 'NOT DEFERRABLE', true];
        }
        yield ['DomainConstraintElem', 'NOT', 'NOT VALID', false];
        yield ['DomainConstraintElem', 'NOT', 'NO INHERIT', true];
        yield ['DomainConstraintElem', 'NOT', 'DEFERRABLE', false];
        yield ['CreateTrigStmt', 'CREATE', 'NOT VALID', false];
        yield ['CreateTrigStmt', 'CREATE', 'DEFERRABLE', true];
        yield ['alter_table_cmd', 'ALTER', 'NO INHERIT', false];
        yield ['unrelated', 'CHECK', 'DEFERRABLE', true];
    }
}
