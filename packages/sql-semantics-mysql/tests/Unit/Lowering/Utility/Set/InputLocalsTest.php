<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Utility\Set\InputLocals;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(InputLocals::class)]
#[Medium]
final class InputLocalsTest extends TestCase
{
    public function testObserveClosesBlockLocalsWhileKeepingParameters(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new Lowering(Productions::load(dirname(__DIR__, 5) . '/resources/productions/mysql-8.4.7.php'), new Leaves(), $profile);
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('CREATE PROCEDURE p(arg INT) BEGIN DECLARE names INT; END');
        $locals = new InputLocals();
        $locals->observe($tree->find('sp_pdparam')[0], $lowering);
        $locals->observe($tree->find('sp_decl_idents')[0], $lowering);

        self::assertTrue($locals->has(new Name('names')));
        $locals->observe($tree->find('sp_block_content')[0], $lowering);
        self::assertFalse($locals->has(new Name('names')));
        self::assertTrue($locals->has(new Name('arg')));
    }

    public function testHasUsesTheProgramVariableIdentity(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $lowering = new Lowering(Productions::load(dirname(__DIR__, 5) . '/resources/productions/mysql-8.4.7.php'), new Leaves(), $profile);
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('CREATE PROCEDURE p() BEGIN DECLARE `NAMÉS` INT; END');
        $locals = new InputLocals();
        $locals->observe($tree->find('sp_decl_idents')[0], $lowering);

        self::assertTrue($locals->has(new Name('names')));
        self::assertFalse($locals->has(new Name('other')));
    }
}
