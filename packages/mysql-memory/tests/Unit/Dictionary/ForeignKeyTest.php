<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\ForeignKey;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceOption;

#[CoversClass(ForeignKey::class)]
#[Small]
final class ForeignKeyTest extends TestCase
{
    public function testTextWritesTheKeyAsShowCreateTableDoes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE DATABASE e; USE d; CREATE TABLE e.p (id INT PRIMARY KEY); CREATE TABLE c (a INT, FOREIGN KEY (a) REFERENCES e.p(id) ON DELETE RESTRICT ON UPDATE NO ACTION)');
        $table = $session->instance->dictionary->table('d', 'c');

        self::assertNotNull($table);
        self::assertSame('CONSTRAINT `c_ibfk_1` FOREIGN KEY (`a`) REFERENCES `e`.`p` (`id`) ON DELETE RESTRICT', $table->definition->foreignKeys[0]->text($table->definition));
    }

    public function testRetargetedReferencesATableUnderAnotherName(): void
    {
        $key = new ForeignKey('fk', [0], 'd', 'p', ['id'], ReferenceOption::Cascade);

        self::assertSame(['d', 'q', ReferenceOption::Cascade], [$key->retargeted('d', 'q')->parentSchema, $key->retargeted('d', 'q')->parentTable, $key->retargeted('d', 'q')->onDelete]);
    }

    public function testReferencesTellsWhetherTheKeyReferencesATable(): void
    {
        $key = new ForeignKey('fk', [0], 'd', 'p', ['id']);

        self::assertSame([true, false], [$key->references('d', 'p'), $key->references('e', 'p')]);
    }

    public function testTextLeavesOutRestrictInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE p (id INT PRIMARY KEY); CREATE TABLE c (a INT, FOREIGN KEY (a) REFERENCES p(id) ON DELETE RESTRICT ON UPDATE CASCADE)');
        $table = $session->instance->dictionary->table('d', 'c');

        self::assertNotNull($table);
        self::assertSame(['CONSTRAINT `c_ibfk_1` FOREIGN KEY (`a`) REFERENCES `p` (`id`) ON UPDATE CASCADE', 'CONSTRAINT `c_ibfk_1` FOREIGN KEY (`a`) REFERENCES `d`.`p` (`id`)'], [$table->definition->foreignKeys[0]->text($table->definition, true), $table->definition->foreignKeys[0]->text($table->definition, true, true)]);
    }
}
