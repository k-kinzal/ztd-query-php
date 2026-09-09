<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\Generation\Rewrite\GeneratedColumnRule;

#[CoversClass(GeneratedColumnRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
final class GeneratedColumnRuleTest extends TestCase
{
    public function testRewriteConvertsSerialOnlyOnGeneratedFields(): void
    {
        $trace = new DerivationTrace('field_def');
        $trace->expand(0, new Production([new NonTerminal('type'), new Terminal('AS'), new NonTerminal('expr')]), 1);
        $trace->expand(0, new Production([new Terminal('SERIAL_SYM')]), 0);
        $trace->expand(2, new Production([new Terminal('NUM')]), 0);
        $input = $trace->terminals();
        $rule = new GeneratedColumnRule();
        $result = $rule->rewrite($input);
        self::assertSame(['BIGINT_SYM', 'UNSIGNED_SYM', 'AS', 'NUM'], $result->names());
        self::assertSame($input->terminals[2], $result->terminals[3]);
        self::assertSame($input->terminals[0]->id, $result->terminals[0]->id);
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @param list<string> $attribute
     * @param list<string> $expected
     */
    #[DataProvider('providerAttributes')]
    public function testRewriteRemovesOnlyAttributesForbiddenOnGeneratedFields(array $attribute, array $expected): void
    {
        $trace = new DerivationTrace('field_def');
        $trace->expand(0, new Production([new NonTerminal('type'), new Terminal('AS'), new NonTerminal('expr'), new NonTerminal('column_attribute')]), 1);
        $trace->expand(0, new Production([new Terminal('INT_SYM')]), 0);
        $trace->expand(2, new Production([new Terminal('NUM')]), 0);
        $trace->expand(3, new Production(array_map(static fn (string $name): Terminal => new Terminal($name), $attribute)), 0);
        $input = $trace->terminals();
        $rule = new GeneratedColumnRule();
        $result = $rule->rewrite($input);
        self::assertSame(['INT_SYM', 'AS', 'NUM', ...$expected], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function providerAttributes(): iterable
    {
        yield 'default' => [['DEFAULT_SYM', 'NUM'], []];
        yield 'on update' => [['ON_SYM', 'UPDATE_SYM', 'NOW_SYM'], []];
        yield 'auto increment' => [['AUTO_INC'], []];
        yield 'serial default' => [['SERIAL_SYM', 'DEFAULT_SYM', 'VALUE_SYM'], []];
        yield 'format' => [['COLUMN_FORMAT_SYM', 'DEFAULT_SYM'], []];
        yield 'storage' => [['STORAGE_SYM', 'DISK_SYM'], []];
        yield 'not null' => [['NOT_SYM', 'NULL_SYM'], ['NOT_SYM', 'NULL_SYM']];
        yield 'comment' => [['COMMENT_SYM', 'TEXT_STRING'], ['COMMENT_SYM', 'TEXT_STRING']];
        yield 'removed attribute' => [[], []];
    }

    public function testRewritePreservesOrdinarySerialFieldsAndTheirDefaults(): void
    {
        $trace = new DerivationTrace('field_def');
        $trace->expand(0, new Production([new NonTerminal('type'), new NonTerminal('column_attribute')]), 0);
        $trace->expand(0, new Production([new Terminal('SERIAL_SYM')]), 0);
        $trace->expand(1, new Production([new Terminal('DEFAULT_SYM'), new Terminal('NUM')]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new GeneratedColumnRule())->rewrite($input));
    }
}
