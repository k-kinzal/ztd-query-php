<?php

declare(strict_types=1);

namespace Tests\Unit\System\Schema;

use MySqlMemory\Instance;
use MySqlMemory\System\Schema\Constraints;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Constraints::class)]
#[Small]
final class ConstraintsTest extends TestCase
{
    public function testKeysAnswersTheUniqueKeysByName(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE p (id INT AUTO_INCREMENT PRIMARY KEY, code CHAR(3) NOT NULL UNIQUE, name VARCHAR(40), amount DECIMAL(8,2), KEY k_name (name(10), amount DESC))');
        $s->query("CREATE TABLE c (id BIGINT UNSIGNED NOT NULL, pid INT, code CHAR(3), qty SMALLINT CHECK (qty > 0), t TEXT, PRIMARY KEY (id), CONSTRAINT fk_p FOREIGN KEY (pid) REFERENCES p (id) ON DELETE CASCADE, FOREIGN KEY (code) REFERENCES p (code), CONSTRAINT ck_t CHECK (t <> 'z'), FULLTEXT KEY ft (t))");
        $s->query("INSERT INTO p (code) VALUES ('aaa'), ('bbb')");
        $table = $s->instance->dictionary->table('d', 'p');
        self::assertNotNull($table);

        self::assertSame([['code', 'PRIMARY'], ['PRIMARY', 'code']], [array_map(static fn ($key): string => $key->name, Constraints::keys($table->definition)), array_map(static fn ($key): string => $key->name, Constraints::keys($table->definition, false))]);
    }

    public function testForeignAnswersTheForeignKeysByName(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE p (id INT AUTO_INCREMENT PRIMARY KEY, code CHAR(3) NOT NULL UNIQUE, name VARCHAR(40), amount DECIMAL(8,2), KEY k_name (name(10), amount DESC))');
        $s->query("CREATE TABLE c (id BIGINT UNSIGNED NOT NULL, pid INT, code CHAR(3), qty SMALLINT CHECK (qty > 0), t TEXT, PRIMARY KEY (id), CONSTRAINT fk_p FOREIGN KEY (pid) REFERENCES p (id) ON DELETE CASCADE, FOREIGN KEY (code) REFERENCES p (code), CONSTRAINT ck_t CHECK (t <> 'z'), FULLTEXT KEY ft (t))");
        $s->query("INSERT INTO p (code) VALUES ('aaa'), ('bbb')");
        $table = $s->instance->dictionary->table('d', 'c');
        self::assertNotNull($table);

        self::assertSame(['c_ibfk_1', 'fk_p'], array_map(static fn ($key): string => $key->name, Constraints::foreign($table->definition)));
    }

    public function testChecksAnswersTheChecksByName(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE p (id INT AUTO_INCREMENT PRIMARY KEY, code CHAR(3) NOT NULL UNIQUE, name VARCHAR(40), amount DECIMAL(8,2), KEY k_name (name(10), amount DESC))');
        $s->query("CREATE TABLE c (id BIGINT UNSIGNED NOT NULL, pid INT, code CHAR(3), qty SMALLINT CHECK (qty > 0), t TEXT, PRIMARY KEY (id), CONSTRAINT fk_p FOREIGN KEY (pid) REFERENCES p (id) ON DELETE CASCADE, FOREIGN KEY (code) REFERENCES p (code), CONSTRAINT ck_t CHECK (t <> 'z'), FULLTEXT KEY ft (t))");
        $s->query("INSERT INTO p (code) VALUES ('aaa'), ('bbb')");
        $table = $s->instance->dictionary->table('d', 'c');
        self::assertNotNull($table);

        self::assertSame(['c_chk_1', 'ck_t'], array_map(static fn ($check): string => $check->name, Constraints::checks($table->definition)));
    }

    public function testReferencedNamesTheKeyAForeignKeyReferences(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE p (id INT AUTO_INCREMENT PRIMARY KEY, code CHAR(3) NOT NULL UNIQUE, name VARCHAR(40), amount DECIMAL(8,2), KEY k_name (name(10), amount DESC))');
        $s->query("CREATE TABLE c (id BIGINT UNSIGNED NOT NULL, pid INT, code CHAR(3), qty SMALLINT CHECK (qty > 0), t TEXT, PRIMARY KEY (id), CONSTRAINT fk_p FOREIGN KEY (pid) REFERENCES p (id) ON DELETE CASCADE, FOREIGN KEY (code) REFERENCES p (code), CONSTRAINT ck_t CHECK (t <> 'z'), FULLTEXT KEY ft (t))");
        $s->query("INSERT INTO p (code) VALUES ('aaa'), ('bbb')");
        $table = $s->instance->dictionary->table('d', 'c');
        self::assertNotNull($table);

        self::assertSame(['PRIMARY', 'code'], array_map(static fn ($key): ?string => Constraints::referenced($key, $s->instance->dictionary), $table->definition->foreignKeys));
    }
}
