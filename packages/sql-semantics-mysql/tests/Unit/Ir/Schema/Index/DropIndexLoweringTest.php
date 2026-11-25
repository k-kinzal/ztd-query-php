<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Alter\DropIndex as ParsedDropIndex;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DropIndexLowering::class)]
#[Medium]
final class DropIndexLoweringTest extends TestCase
{
    public function testLowerUsesTheVisibleTemporaryOwnerAndNativeCase(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE TABLE app.events (n INT, INDEX Élan (n))',
            'CREATE TEMPORARY TABLE app.events (n INT NOT NULL, UNIQUE INDEX ÉLAN (n))',
        ]);
        $drop = $semantics->analyze('DROP INDEX élan ON app.events ALGORITHM=DEFAULT LOCK=DEFAULT', $context);

        self::assertInstanceOf(DropIndex::class, $drop);
        self::assertSame(ExternalRelationKind::TemporaryTable, $drop->target->owner->kind);
        self::assertSame(IndexRole::ClusteredUnique, $drop->target->role);
        self::assertSame('DROP INDEX `ÉLAN` ON `app`.`events`', $drop->toString());
    }

    public function testRemovalBindsOnlyCurrentGeneratingAlternatives(): void
    {
        $context = (new Semantics())->context([
            'CREATE TABLE app.events (id INT AUTO_INCREMENT PRIMARY KEY, KEY alternate (id))',
        ]);
        $operation = (new Inspection())->inspect('DROP INDEX alternate ON app.events', $context)->binding();
        $statement = $operation->statement;
        self::assertInstanceOf(ParsedDropIndex::class, $statement);
        $bound = (new DropIndexLowering())->removal($operation, $statement, $statement->index);

        self::assertSame('alternate', $bound->index->name->value);
        $increment = $bound->autoIncrement;
        self::assertNotNull($increment);
        self::assertSame('id', $increment->field->value);
        self::assertSame('PRIMARY', $increment->alternates[0]->value);
    }

    public function testIndexKeepsAnImplicitClusteredKeyUnderItsDeclaredUniqueName(): void
    {
        $context = (new Semantics())->context(['CREATE TABLE app.events (n INT NOT NULL, UNIQUE (n))']);
        self::assertInstanceOf(AnalysisContext::class, $context);

        $index = (new DropIndexLowering())->index($context->tables[0], new Name('N'));
        self::assertSame('n', $index->name->value);
        self::assertSame(PhysicalIndexKind::Unique, $index->kind);
        self::assertTrue($index->clusteredUnique);
    }

    public function testLowerDistinguishesTwoRequiredUniqueKeysAndAnExplicitPrimary(): void
    {
        $semantics = new Semantics();
        $keys = 'n INT NOT NULL, m INT NOT NULL, UNIQUE first_key (n), UNIQUE later_key (m)';
        $context = $semantics->context(["CREATE TABLE app.events ($keys)"]);
        $first = $semantics->analyze('DROP INDEX first_key ON app.events', $context);
        $later = $semantics->analyze('DROP INDEX later_key ON app.events ALGORITHM=INPLACE LOCK=NONE', $context);
        self::assertInstanceOf(DropIndex::class, $first);
        self::assertInstanceOf(DropIndex::class, $later);
        self::assertSame(IndexRole::ClusteredUnique, $first->target->role);
        self::assertSame(IndexRole::Unique, $later->target->role);

        $context = $semantics->context(["CREATE TABLE app.events ($keys, PRIMARY KEY (m))"]);
        $first = $semantics->analyze('DROP INDEX first_key ON app.events ALGORITHM=INPLACE LOCK=NONE', $context);
        self::assertInstanceOf(DropIndex::class, $first);
        self::assertSame(IndexRole::Unique, $first->target->role);
    }

    public function testIdentityUsesTheLegacyQualifiedKeyOnTheSelectedTable(): void
    {
        $semantics = new Semantics('mysql-5.7.44');
        $catalog = $semantics->context(['CREATE TABLE app.events (n INT NOT NULL, INDEX by_n (n))']);
        $result = $semantics->analyze('ALTER TABLE app.events DROP KEY app.events.by_n', $catalog);

        self::assertInstanceOf(DropIndex::class, $result);
        self::assertSame('DROP INDEX `by_n` ON `app`.`events`', $result->toString());
    }
}
