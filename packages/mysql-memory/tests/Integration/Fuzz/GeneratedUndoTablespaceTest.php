<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Audit\Storage\UndoState;
use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Metadata;
use Fuzz\Structure\Profiles;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement as M;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class GeneratedUndoTablespaceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Server\Storage\DropUndoTablespace}>
     * @throws JsonException When the semantic inventory cannot be decoded
     * @throws ReflectionException When constructor metadata cannot be read
     */
    public static function providerStatements(): iterable
    {
        $catalogue = new Catalogue();
        $roots = $catalogue->roots();
        $choices = new Choices('row-roles-20261017:168');
        $choices->one($roots);
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($roots[168], 6);
        Check::invariant($node instanceof M\Server\Storage\DropUndoTablespace, 'The recorded whole-model root retains its undo tablespace identity.');
        $initial = substr((new Profiles())->select($node)->grammar->value, 6);
        foreach (array_unique([$initial, '8.4.7', '9.1.0']) as $release) {
            yield $release => [$release, $node];
        }
    }

    #[DataProvider('providerStatements')]
    public function testTheSameDropSeesMissingActiveEmptyAndRemovedUndoState(string $release, M\Server\Storage\DropUndoTablespace $node): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $session->text = 'opaque request label';
                $reference = new Reference();
                $profile = $session->semantics()->profile();
                $sql = $reference->sql($node, $profile);
                $missing = (new Native())->observe($native, $sql);
                self::assertSame($missing, (new Memory())->observe($session, $node));
                self::assertSame(3510, $missing['error'][0] ?? null);

                $create = new M\Server\Storage\CreateUndoTablespace($node->name, new M\Literal\Text('generated_undo_fixture.ibu'));
                $created = (new Native())->observe($native, $reference->sql($create, $profile));
                self::assertSame($created, (new Memory())->observe($session, $create));
                self::assertArrayNotHasKey('error', $created);
                $active = (new Native())->observe($native, $sql);
                self::assertSame($active, (new Memory())->observe($session, $node));
                self::assertSame(1529, $active['error'][0] ?? null);

                $deactivate = new M\Server\Storage\AlterUndoTablespace($node->name, false);
                $inactive = (new Native())->observe($native, $reference->sql($deactivate, $profile));
                self::assertSame($inactive, (new Memory())->observe($session, $deactivate));
                self::assertArrayNotHasKey('error', $inactive);
                $states = (new UndoState())->empty($native, $node->name);
                self::assertSame('empty', $states[count($states) - 1]);
                $empty = (new Native())->observe($native, $sql);
                self::assertSame($empty, (new Memory())->observe($session, $node));
                self::assertArrayNotHasKey('error', $empty);
                $removed = (new Native())->observe($native, $sql);
                self::assertSame($removed, (new Memory())->observe($session, $node));
                self::assertSame(3510, $removed['error'][0] ?? null);
                self::assertSame('opaque request label', $session->text);
                self::assertSame($sql, $reference->sql($node, $profile));
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
