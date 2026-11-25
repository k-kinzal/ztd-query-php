<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ForeignKeyDeclarations;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ForeignKeyDeclarations::class)]
#[Medium]
final class ForeignKeyDeclarationsTest extends TestCase
{
    public function testCollectBindsUnqualifiedParentsInTheChildDatabase(): void
    {
        $semantics = new Semantics();
        $catalog = $semantics->context([
            'CREATE TABLE a.p (id INT PRIMARY KEY)',
            'CREATE TABLE b.p (id INT PRIMARY KEY)',
            'CREATE TABLE b.c (id INT, pid INT, FOREIGN KEY (pid) REFERENCES p (id))',
        ]);

        self::assertInstanceOf(AnalysisContext::class, $catalog);
        self::assertCount(1, $catalog->foreignKeys);
        self::assertSame('b', $catalog->foreignKeys[0]->parent->schema?->value);
    }

    public function testInlineChangesAtMySql9(): void
    {
        $references = new ForeignKeyDeclarations();

        self::assertFalse($references->inline(GrammarRelease::MySql847));
        self::assertTrue($references->inline(GrammarRelease::MySql901));
    }

    public function testReferenceTreatsInlineDeclarationsAccordingToTheirRelease(): void
    {
        $parsed = (new Inspection())->inspect('CREATE TABLE app.c (pid INT REFERENCES app.p (id))')->statement;
        self::assertInstanceOf(CreateTable::class, $parsed);
        $references = new ForeignKeyDeclarations();

        self::assertNull($references->reference($parsed->elements[0], false));
        self::assertSame('p', $references->reference($parsed->elements[0], true)?->table->name->value);
    }

    public function testChildFieldsKeepConstraintOrder(): void
    {
        $parsed = (new Inspection())->inspect('CREATE TABLE app.c (pid INT, other INT, FOREIGN KEY (other, pid) REFERENCES app.p (a, b))')->statement;
        self::assertInstanceOf(CreateTable::class, $parsed);
        $constraint = $parsed->elements[2];
        self::assertInstanceOf(ForeignKey::class, $constraint);
        $fields = (new ForeignKeyDeclarations())->childFields($constraint);

        self::assertSame('other', $fields[0]->value);
        self::assertSame('pid', $fields[1]->value);
    }
}
