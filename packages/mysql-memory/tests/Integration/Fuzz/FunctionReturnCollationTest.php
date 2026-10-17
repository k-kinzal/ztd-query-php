<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Choices;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class FunctionReturnCollationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $types = ['CHAR(0) BINARY', 'CHAR(1) BINARY CHARSET BINARY', 'CHAR(1) CHARSET BINARY BINARY', 'CHAR(1) BINARY CHARSET latin1', 'CHAR(1) ASCII BINARY', 'CHAR(1) BINARY ASCII', 'CHAR(1) UNICODE BINARY', 'NCHAR(1) BINARY', "ENUM('a') BINARY", "ENUM('a') BINARY CHARSET BINARY", "SET('a') BINARY", 'CHAR(1) BYTE', 'CHAR(1) CHARSET BINARY', 'CHAR(1) BINARY CHARSET missing', "ENUM('a') BINARY CHARSET latin1", "ENUM('a') ASCII BINARY", "ENUM('a') CHARSET latin1 BINARY", 'CHAR(1) ASCII', 'CHAR(1) UNICODE'];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = ['CREATE DATABASE d', 'USE d'];
            foreach ($types as $type => $declared) {
                foreach (["RETURN 'a'", 'SHUTDOWN', "'unfinished"] as $body => $instruction) {
                    $statements[] = 'CREATE FUNCTION f' . $type . '_' . $body . '() RETURNS ' . $declared . ' ' . $instruction;
                }
            }
            $statements[] = 'SET GLOBAL log_bin_trust_function_creators=1';
            foreach ($types as $index => $declared) {
                $statements[] = 'CREATE FUNCTION allowed' . $index . '() RETURNS ' . $declared . " RETURN 'a'";
                $statements[] = 'SELECT allowed' . $index . '()';
                $statements[] = 'SELECT COLLATION(allowed' . $index . '()), CHARSET(allowed' . $index . '())';
                $statements[] = 'SHOW CREATE FUNCTION allowed' . $index;
            }
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements
     */
    #[DataProvider('providerReleases')]
    public function testReturnCollationsRetainTheirTypeAndReleaseRules(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);
                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }

    /**
     * @return iterable<string, array{string, list<CreateFunction>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('function-return-collation-history-20261017');
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $structures = [];
            for ($trial = 0; $trial < 12; $trial++) {
                $charset = new \SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Ascii, mark: \SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark::Trailing);
                $length = (string) $choices->integer(1, 20);
                $type = $trial % 2 === 0
                    ? new \SqlSemantics\Platform\MySql\Statement\Type\Character(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::Char, $length, charset: $charset)
                    : new \SqlSemantics\Platform\MySql\Statement\Type\Enumeration(\SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind::Enum, [new \SqlSemantics\Platform\MySql\Statement\Literal\Text('a')], new \SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Binary));
                $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('f'), new \SqlSemantics\Statement\Identifier\Name('d' . $trial));
                $body = new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['a'])));
                $structures[] = new CreateFunction($name, new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), $type, $body);
            }
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<CreateFunction> $structures
     */
    #[DataProvider('providerStructures')]
    public function testUnchangedConstructedReturnsSurviveDatabaseAndRoutineTransitions(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('SET GLOBAL log_bin_trust_function_creators=1');
            $session = (new Instance($release))->connect();
            $session->query('SET GLOBAL log_bin_trust_function_creators=1');
            $observations = array_map(static function (CreateFunction $definition) use ($native, $session): array {
                $sql = (new \Fuzz\Structure\Reference())->sql($definition, $session->semantics()->profile());
                self::assertNotNull($definition->name->schema);
                $database = $definition->name->schema->value;
                $history = array_map(static function (string $change) use ($native, $session, $definition, $sql): array {
                    $native->query($change);
                    $session->query($change);
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, $sql), (new Memory())->observe($session, $definition)];
                }, ['SET @unrelated=1', 'CREATE DATABASE ' . $database, 'SET @unrelated=2', 'DROP FUNCTION ' . $database . '.f']);
                self::assertSame([1049, null, 1304, null], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
                return $history;
            }, $structures);
            $flat = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($flat, 0)), array_map(serialize(...), array_column($flat, 1)));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
