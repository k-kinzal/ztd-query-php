<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\Dependencies;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dependencies::class)]
#[Medium]
final class DependenciesTest extends TestCase
{
    public function testCaptureUsesStoredViewIdentitiesAndPermanentDependencyDeclarations(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE VIEW v AS SELECT a FROM t; CREATE VIEW w AS SELECT a FROM v; ALTER TABLE t ADD b INT; CREATE TEMPORARY TABLE t(a VARCHAR(12))');
        $dictionary = $session->instance->dictionary;
        $schema = $dictionary->schema('d');
        self::assertNotNull($schema);
        $binding = \MySqlMemory\Session\Statement\Catalog::snapshot($session)->view($schema->views['w']);

        $dependencies = Dependencies::capture([$binding->declaration], $session);

        self::assertSame([
            ['d', 'w', $schema->views['w'], false],
            ['d', 'v', $schema->views['v'], true],
            ['d', 't', $dictionary->table('d', 't')?->definition->declaration, true],
        ], $dependencies);
        self::assertSame([], Dependencies::capture([], $session));
    }
}
