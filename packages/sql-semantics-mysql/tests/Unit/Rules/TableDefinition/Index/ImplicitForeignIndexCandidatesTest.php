<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\ImplicitForeignIndexCandidates;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\IndexCandidate;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ImplicitForeignIndexCandidates::class)]
#[Medium]
final class ImplicitForeignIndexCandidatesTest extends TestCase
{
    public function testCollectUsesOnlyTheEffectiveInlineReference(): void
    {
        $parsed = (new Inspection())->inspect('CREATE TABLE app.c (pid INT REFERENCES app.p(id))')->statement;
        self::assertInstanceOf(CreateTable::class, $parsed);
        $rule = new ImplicitForeignIndexCandidates();
        $columns = ['pid' => [new Name('pid'), false, false]];
        self::assertSame([], $rule->collect($parsed, $columns, [[]], GrammarRelease::MySql847));
        $generated = $rule->collect($parsed, $columns, [[]], GrammarRelease::MySql910);
        self::assertNotNull($generated);
        self::assertSame('pid', $generated[0]->firstField?->value);
    }

    public function testProposeUsesTheConstraintSymbolAndOriginalFieldCase(): void
    {
        $parsed = (new Inspection())->inspect(
            'CREATE TABLE app.c (PID INT, CONSTRAINT symbol_fk FOREIGN KEY written_name (pid DESC) REFERENCES app.p(id))'
        )->statement;
        self::assertInstanceOf(CreateTable::class, $parsed);
        $clause = $parsed->elements[1];
        self::assertInstanceOf(ForeignKey::class, $clause);
        $name = new Name('PID');
        $rule = new ImplicitForeignIndexCandidates();
        $candidate = $rule->propose($clause, ['pid' => [$name, false, false]], true);
        self::assertNotNull($candidate);

        self::assertSame('symbol_fk', $candidate->writtenName?->value);
        self::assertSame([[$name,true]], $candidate->prefix);
        self::assertNull($rule->propose($clause, ['pid' => [$name, false, false]], false));
    }

    public function testPruneRetainsTheLaterLongestEquivalentProposal(): void
    {
        $x = new Name('x');
        $y = new Name('y');
        $short = new IndexCandidate(IndexKind::Index, new Name('short'), $x, true, false, null, [[$x,false]]);
        $old = new IndexCandidate(IndexKind::Index, new Name('displaced'), $x, true, false, null, [[$x,false],[$y,false]]);
        $last = new IndexCandidate(IndexKind::Index, new Name('retained'), $x, true, false, null, [[$x,false],[$y,false]]);

        self::assertSame([2 => $last], (new ImplicitForeignIndexCandidates())->prune([$short,$old,$last], []));
    }

    public function testCoversRequiresTheNativeDirectionOfEachLeadingField(): void
    {
        $x = new Name('x');
        $y = new Name('y');
        $required = new IndexCandidate(IndexKind::Index, null, $x, true, false, null, [[$x,false]]);
        $descending = new IndexCandidate(IndexKind::Index, null, $x, true, false, null, [[$x,true],[$y,false]]);
        $ascending = new IndexCandidate(IndexKind::Unique, null, $x, true, false, null, [[$x,false],[$y,true]]);
        $rule = new ImplicitForeignIndexCandidates();

        self::assertFalse($rule->covers($descending, $required));
        self::assertTrue($rule->covers($ascending, $required));
    }
}
