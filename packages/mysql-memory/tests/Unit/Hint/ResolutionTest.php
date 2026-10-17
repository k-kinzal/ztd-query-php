<?php

declare(strict_types=1);

namespace Tests\Unit\Hint;

use MySqlMemory\Hint\Blocks;
use MySqlMemory\Hint\Printer;
use MySqlMemory\Hint\Registration;
use MySqlMemory\Hint\Resolution;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;

#[CoversClass(Resolution::class)]
#[Small]
final class ResolutionTest extends TestCase
{
    public function testResolveWarnsAboutTablesFirstAndThenIndexes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, KEY ka (a))');
        $blocks = (new Blocks($session->instance->dictionary, 'd'))->read($session->analyze('SELECT /*+ MRR(x ka) MRR(x) INDEX(t KA, kz) JOIN_ORDER(y, t@`select#1`, z@`select#1`) QB_NAME(Qq) BKA(@QQ w) */ 1 FROM t')->statement);
        $registration = (new Registration($blocks, SystemVariables::of(GrammarRelease::MySql847), new Printer()))->register();

        self::assertSame([
            [3128, 'Unresolved name `x`@`Qq` for MRR hint'],
            [3128, 'Unresolved name `y` for JOIN_ORDER hint'],
            [3128, 'Unresolved name `w`@`Qq` for BKA hint'],
            [3128, 'Unresolved name `x`@`Qq` `ka` for MRR hint'],
            [3128, 'Unresolved name `t`@`Qq` `kz` for INDEX hint'],
        ], (new Resolution($registration, new Printer()))->resolve()->warnings);
    }

    public function testResolveFollowsTheBlocksInTheOrderTheServerSetsThemUp(): void
    {
        $session = (new Instance())->connect();
        $blocks = (new Blocks($session->instance->dictionary, ''))->read($session->analyze('SELECT /*+ BKA(x1) */ (SELECT /*+ BKA(x2) */ 1) FROM (SELECT /*+ BKA(x3) */ 1) d')->statement);
        $registration = (new Registration($blocks, SystemVariables::of(GrammarRelease::MySql847), new Printer()))->register();

        self::assertSame(['`x1`@`select#1`', '`x3`@`select#3`', '`x2`@`select#2`'], array_map(static fn (array $warning): string => explode(' ', $warning[1])[2], (new Resolution($registration, new Printer()))->resolve()->warnings));
    }

    public function testWarnRecordsAnUnresolvedName(): void
    {
        $resolution = new Resolution(new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer()), new Printer());
        $resolution->warn('`t`', 'BKA');

        self::assertSame([[3128, 'Unresolved name `t` for BKA hint']], $resolution->warnings);
    }
}
