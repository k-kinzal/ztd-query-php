<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Exchange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\IndexAdditionLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(IndexAdditionLowering::class)]
#[Medium]
final class IndexAdditionLoweringTest extends TestCase
{
    public function testRequestUsesCanonicalWholeFieldsAndNativeDirection(): void
    {
        $context = (new Semantics())->context(['CREATE TABLE app.child (id INT PRIMARY KEY, code INT)']);
        self::assertInstanceOf(AnalysisContext::class, $context);
        $statement = new IndexDefinition(IndexKind::Unique, [new ColumnPart(new Name('CODE'), direction: Direction::Descending)]);
        [$label, $fields, $role] = (new IndexAdditionLowering())->request(
            $context->tables[0],
            NativeAdditionFixture::owner('child'),
            $statement,
            true
        );

        self::assertNull($label);
        self::assertSame('code', $fields[0]->field->value);
        self::assertFalse($fields[0]->descending);
        self::assertSame(IndexRole::Unique, $role);
    }

    public function testCompleteStoresTheAllocatedName(): void
    {
        $context = (new Semantics())->context(['CREATE TABLE app.child (id INT PRIMARY KEY, code INT)']);
        self::assertInstanceOf(AnalysisContext::class, $context);
        $owner = NativeAdditionFixture::owner('child');
        $lowering = new IndexAdditionLowering();
        $request = $lowering->request(
            $context->tables[0],
            $owner,
            new IndexDefinition(IndexKind::Index, [new ColumnPart(new Name('code'))]),
            false
        );
        $edit = $lowering->complete($owner, new Identifier('code_2'), $request);

        self::assertTrue($edit->automatic);
        self::assertSame('code_2', $edit->key->identity->name->value);
    }
}
