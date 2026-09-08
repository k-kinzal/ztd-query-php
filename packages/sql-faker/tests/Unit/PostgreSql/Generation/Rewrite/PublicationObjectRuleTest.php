<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\PostgreSql\Generation\Rewrite\PublicationObjectRule;

#[CoversClass(PublicationObjectRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
final class PublicationObjectRuleTest extends TestCase
{
    public function testRewriteCompletesTheFirstImplicitObjectAndPreservesContinuation(): void
    {
        $trace = new DerivationTrace('pub_obj_list');
        $trace->expand(0, new Production([new NonTerminal('PublicationObjSpec'), new Terminal(','), new NonTerminal('PublicationObjSpec')]), 1);
        $trace->expand(0, new Production([new Terminal('IDENT')]), 0);
        $trace->expand(2, new Production([new Terminal('IDENT')]), 0);
        $input = $trace->terminals();
        $result = (new PublicationObjectRule())->rewrite($input);
        self::assertSame(['TABLE', 'IDENT', ',', 'IDENT'], $result->names());
        self::assertSame($input->original, $result->original);
    }

    public function testKindRecognizesCurrentSchemaAndInheritsAnUnqualifiedName(): void
    {
        $trace = new DerivationTrace('PublicationObjSpec');
        $trace->expand(0, new Production([new Terminal('CURRENT_SCHEMA')]), 0);
        $rule = new PublicationObjectRule();
        self::assertSame('TABLES', $rule->kind($trace->terminals(), 0, 'TABLE'));
        self::assertSame('TABLES', $rule->kind(TerminalSequence::fromNames(['IDENT']), 0, 'TABLES'));
    }
}
