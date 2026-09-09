<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Query\SchemaElementsRule;

#[CoversClass(SchemaElementsRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class SchemaElementsRuleTest extends TestCase
{
    #[DataProvider('providerSchemas')]
    public function testRewriteConditionalSchemasKeepElementsOrAnEmptyConditional(bool $conditional, bool $elements): void
    {
        $names = $conditional ? ['CREATE', 'SCHEMA', 'IF_P', 'NOT', 'EXISTS', 'IDENT'] : ['CREATE', 'SCHEMA', 'IDENT'];
        $terminals = array_map(static fn (int $id, string $name): TerminalOccurrence =>
            new TerminalOccurrence($name, $id + 10, [0], ['CreateSchemaStmt']), array_keys($names), $names);
        $terminals = [...$terminals, ...($elements ? [new TerminalOccurrence('CREATE', 20, [0, 1], ['CreateSchemaStmt', 'OptSchemaEltList'])] : [])];
        $input = new TerminalSequence($terminals, $terminals, [], [
            new ProductionOccurrence(0, null, 'CreateSchemaStmt', 0), new ProductionOccurrence(1, 0, 'OptSchemaEltList', 0),
        ]);
        $rule = new SchemaElementsRule();
        $result = $rule->rewrite($input);
        $names = $conditional && $elements ? [...array_slice($names, 0, 2), ...array_slice($names, 5)] : $names;
        self::assertSame($elements ? [...$names, 'CREATE'] : $names, $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{bool, bool}>
     */
    public static function providerSchemas(): iterable
    {
        yield [true, true];
        yield [false, true];
        yield [true, false];
        yield [false, false];
    }
}
