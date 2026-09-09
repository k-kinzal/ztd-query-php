<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\Generation\Rewrite\RoleGrantRule;

#[CoversClass(RoleGrantRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
final class RoleGrantRuleTest extends TestCase
{
    public function testRewriteChangesPrivilegesToRolesOnlyWithoutAnOnTarget(): void
    {
        $trace = new DerivationTrace('grant');
        $trace->expand(0, new Production([new Terminal('GRANT'), new NonTerminal('role_or_privilege'), new Terminal('TO_SYM'), new Terminal('IDENT')]), 0);
        $trace->expand(1, new Production([new Terminal('CREATE'), new Terminal('TABLESPACE_SYM')]), 29);
        $input = $trace->terminals();
        $result = (new RoleGrantRule())->rewrite($input);
        self::assertSame(['GRANT', 'IDENT_QUOTED', 'TO_SYM', 'IDENT'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame(29, $result->productions[1]->ordinal);
    }

    public function testRewriteKeepsAPrivilegeGrantWithAnExplicitTarget(): void
    {
        $trace = new DerivationTrace('grant');
        $trace->expand(0, new Production([new Terminal('GRANT'), new NonTerminal('role_or_privilege'), new Terminal('ON_SYM'), new NonTerminal('grant_ident')]), 1);
        $trace->expand(1, new Production([new Terminal('SELECT_SYM')]), 2);
        $trace->expand(3, new Production([new Terminal('*'), new Terminal('.'), new Terminal('*')]), 2);
        $input = $trace->terminals();
        self::assertSame($input, (new RoleGrantRule())->rewrite($input));
    }

    public function testRewritePreservesARoleNameButRemovesItsInvalidColumnList(): void
    {
        $trace = new DerivationTrace('revoke');
        $trace->expand(0, new Production([new NonTerminal('role_or_privilege')]), 0);
        $trace->expand(0, new Production([new NonTerminal('role_ident_or_text'), new NonTerminal('opt_column_list')]), 0);
        $trace->expand(0, new Production([new Terminal('IDENT_QUOTED')]), 0);
        $trace->expand(1, new Production([new Terminal('('), new Terminal('IDENT'), new Terminal(')')]), 1);
        self::assertSame(['IDENT_QUOTED'], (new RoleGrantRule())->rewrite($trace->terminals())->names());
    }

    public function testRewriteRemovesAHostOnlyWhenTheRoleIsUsedAsAPrivilege(): void
    {
        $trace = new DerivationTrace('grant');
        $trace->expand(0, new Production([new NonTerminal('role_or_privilege'), new NonTerminal('grant_ident')]), 1);
        $trace->expand(0, new Production([new NonTerminal('role_ident_or_text'), new Terminal('@'), new Terminal('IDENT')]), 1);
        $trace->expand(0, new Production([new Terminal('IDENT_QUOTED')]), 0);
        $trace->expand(3, new Production([new Terminal('*')]), 0);
        $input = $trace->terminals();
        $result = (new RoleGrantRule())->rewrite($input);
        self::assertSame(['IDENT_QUOTED', '*'], $result->names());
        self::assertSame($input->terminals[0], $result->terminals[0]);
        self::assertSame($input->original, $result->original);
        self::assertSame($result, (new RoleGrantRule())->rewrite($result));
    }

    public function testRewriteRemovesColumnListsFromRoutinePrivileges(): void
    {
        $trace = new DerivationTrace('revoke');
        $trace->expand(0, new Production([new NonTerminal('role_or_privilege'), new NonTerminal('opt_acl_type'), new NonTerminal('grant_ident')]), 1);
        $trace->expand(0, new Production([new Terminal('INSERT_SYM'), new NonTerminal('opt_column_list')]), 1);
        $trace->expand(1, new Production([new Terminal('('), new Terminal('IDENT'), new Terminal(')')]), 1);
        $trace->expand(4, new Production([new Terminal('FUNCTION_SYM')]), 2);
        $trace->expand(5, new Production([new Terminal('*')]), 0);
        $input = $trace->terminals();
        self::assertSame(['INSERT_SYM', 'FUNCTION_SYM', '*'], (new RoleGrantRule())->rewrite($input)->names());
    }

    public function testPrivilegePreservesMissingOccurrences(): void
    {
        $input = \SqlFaker\Grammar\Generation\Token\TerminalSequence::fromNames(['SELECT_SYM']);
        self::assertSame($input, (new RoleGrantRule())->privilege($input, 0, 1));
    }
}
