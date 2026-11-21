<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Platform\Sqlite\Model\Lowering\Productions;
use SqlSemantics\Platform\Sqlite\Platform;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(Productions::class)]
#[Medium]
final class ProductionsTest extends TestCase
{
    public function testLoadReadsAReleaseOnce(): void
    {
        $path = dirname(__DIR__, 4) . '/resources/productions/sqlite-3.47.2.php';

        $productions = Productions::load($path);

        self::assertSame($productions, Productions::load($path));
        self::assertContains('term: INTEGER', $productions->all());
    }

    public function testLoadRefusesAMissingList(): void
    {
        $this->expectExceptionMessage('The production list of the grammar release is missing: /nowhere/sqlite-0.0.0.php');

        Productions::load('/nowhere/sqlite-0.0.0.php');
    }

    public function testFormWrapsANodeWithItsSignature(): void
    {
        $productions = new Productions(['where_opt' => ['where_opt:', 'where_opt: WHERE expr']]);
        $node = new Node('where_opt', 1, []);

        $form = $productions->form($node);

        self::assertSame($node, $form->node);
        self::assertSame('where_opt: WHERE expr', $form->signature);
    }

    public function testSignatureSpellsTheProductionOfAParsedNode(): void
    {
        $platform = Platform::shared();
        $profile = (new Semantics())->profile();
        $tree = $platform->parser($profile)->parse('SELECT 1');

        self::assertSame('input: cmdlist', $platform->productions($profile)->signature($tree));
        self::assertSame('cmdlist: ecmd', $platform->productions($profile)->signature($tree->find('cmdlist')[0]));
    }

    public function testSignatureRefusesANodeTheReleaseDoesNotHave(): void
    {
        $productions = new Productions(['where_opt' => ['where_opt:']]);

        $this->expectExceptionMessage('The grammar release has no production where_opt#1.');

        $productions->signature(new Node('where_opt', 1, []));
    }

    public function testAllListsEverySignatureOfEveryRule(): void
    {
        $productions = new Productions(['a' => ['a:', 'a: a B'], 'b' => ['b: C']]);

        self::assertSame(['a:', 'a: a B', 'b: C'], $productions->all());
    }
}
