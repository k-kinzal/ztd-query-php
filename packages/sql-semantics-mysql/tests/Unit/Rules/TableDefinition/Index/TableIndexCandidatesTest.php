<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\TableIndexCandidates;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TableIndexCandidates::class)]
#[Medium]
final class TableIndexCandidatesTest extends TestCase
{
    public function testKeysPrefersAnExplicitKeyNameOverAConstraintLabel(): void
    {
        $result = (new Inspection())->inspect('CREATE TABLE app.t (a INT, CONSTRAINT label UNIQUE INDEX exact_name (a))');
        self::assertInstanceOf(CreateTable::class, $result->statement);

        $keys = (new TableIndexCandidates())->keys($result->statement->elements[1], ['a' => [new Name('a'), false, false]]);
        self::assertNotNull($keys);
        self::assertSame(IndexKind::Unique, $keys[0][0]);
        self::assertSame('exact_name', $keys[0][1]?->value);
    }
    public function testFieldsKeepsNamesAndRejectsNativeCaseDuplicates(): void
    {
        $candidates = new TableIndexCandidates();
        $result = (new \SqlSemantics\Platform\MySql\Semantics())->context(['CREATE TABLE app.t (n INT NOT NULL, m INT NULL)']);
        self::assertInstanceOf(AnalysisContext::class, $result);
        [$required, $nullable] = $result->tables[0]->columns;

        $fields = $candidates->fields([[null, $required], [null, $nullable]]);
        self::assertSame(['n' => [$required->name, true, false], 'm' => [$nullable->name, false, false]], $fields);
        self::assertNull($candidates->fields([[null, $required], [null, $required]]));
    }

    public function testPartsStopFullPrefixAtTheFirstPartialField(): void
    {
        $statement = (new Inspection())->inspect('CREATE TABLE app.c (id INT, label VARCHAR(12), KEY named_idx (id, label(3)))')->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $definition = $statement->elements[2];
        self::assertInstanceOf(IndexDefinition::class, $definition);
        $id = new Name('id');
        $label = new Name('label');

        self::assertSame([false, false, [$id]], (new TableIndexCandidates())->parts($definition, [
            'id' => [$id, true, false], 'label' => [$label, false, false],
        ]));
    }
}
