<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\Generation\Rewrite\RewriteDefinitions;

#[CoversClass(RewriteDefinitions::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenRewriter::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Option\UniqueOptionRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\AlterDatabaseRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\ConstraintEnforcementRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\FlushExportRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\InstanceActionRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\IntegerContextRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\LoadSourceCountRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\RequiredAliasRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\RoleGrantRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\SetNamesRule::class)]
#[UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Expression\ExpressionGroupingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\AlterEventRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\SubqueryContextRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\IntoClauseRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\GeneratedColumnRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\TransactionCompletionRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Alter\OrderByRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Replication\StartRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\FieldListRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Replication\TablePatternRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Expression\QuantifiedComparisonRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Expression\TableValueConstructorRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Query\QueryContextRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Query\JoinGroupingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Column\FieldLengthRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Routine\ReturnRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Name\SystemVariableRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Column\AutoIncrementRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\DefinitionRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\ListValueRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\ValueArityRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Partition\ValueShape::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Routine\LanguageRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalMappingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Query\WindowFrameRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Expression\ConcatenationRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Rewrite\Name\HostNameRule::class)]
final class RewriteDefinitionsTest extends TestCase
{
    public function testCreateComposesTheDeclaredSourceRules(): void
    {
        $trace = new DerivationTrace('option_value_no_option_type');
        $trace->expand(0, new Production([new Terminal('NAMES_SYM'), new Terminal('EQ'), new Terminal('NUM')]), 0);
        $result = (new RewriteDefinitions())->create()->rewrite($trace->terminals());
        self::assertSame(['NAMES_SYM', 'DEFAULT_SYM'], $result->names());
    }

    #[DataProvider('providerDefaultTokens')]
    public function testCreateBindsTheReleaseSpecificDefaultToken(string $version, string $expected): void
    {
        $trace = new DerivationTrace('option_value_no_option_type');
        $trace->expand(0, new Production([new Terminal('NAMES_SYM'), new Terminal('EQ'), new Terminal('NUM')]), 0);
        self::assertSame(['NAMES_SYM', $expected], (new RewriteDefinitions())->create($version)->rewrite($trace->terminals())->names());
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerDefaultTokens(): array
    {
        return [['mysql-5.6.51', 'DEFAULT'], ['mysql-5.7.44', 'DEFAULT'], ['mysql-8.0.44', 'DEFAULT_SYM'], ['mysql-8.4.7', 'DEFAULT_SYM'], ['mysql-9.1.0', 'DEFAULT_SYM']];
    }
}
