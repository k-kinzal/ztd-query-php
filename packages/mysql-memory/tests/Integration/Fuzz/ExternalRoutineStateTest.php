<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Audit\SessionSetup;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineLanguage;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\DollarQuotedText;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ExternalRoutineStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Statement|Scalar>}>
     */
    public static function providerStructures(): iterable
    {
        $nodes = [];
        foreach ([new Text('return 1'), new DollarQuotedText('opaque external code')] as $index => $code) {
            $body = new ExternalBody($code);
            $language = new RoutineLanguage(new Name($index === 0 ? 'JAVASCRIPT' : 'PYTHON'));
            $function = new Name('f' . $index);
            $procedure = new QualifiedName(new Name('p' . $index));
            $nodes[] = new CreateFunction(new QualifiedName($function), new ParameterList([]), new Integral(IntegralKind::Int), $body, characteristics: [new RoutineLanguage(), $language, new Determinism(true)]);
            $nodes[] = new FunctionCall($function);
            $nodes[] = new CreateProcedure($procedure, new ParameterList([]), $body, [$language]);
            $nodes[] = new ProcedureCall($procedure);
        }
        foreach (['8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $nodes];
        }
    }

    /**
     * @param list<Statement|Scalar> $nodes Original declarations and calls reused before selection, after creation and after duplicate creation
     */
    #[DataProvider('providerStructures')]
    public function testExternalDeclarationsAndCallsUseOnlyTheirTypedBodies(string $release, array $nodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->set_charset('utf8mb4');
                $native->query("SET sql_mode='', collation_connection='utf8mb4_bin'");
                $session = (new Instance($release))->connect();
                (new SessionSetup())->apply($session, '');
                $observed = array_map(static function (array $setup) use ($native, $session, $nodes): array {
                    foreach ($setup as $sql) {
                        $native->query($sql);
                        $session->query($sql);
                    }
                    $session->text = 'unrelated invalid input';
                    return array_map(static function (Statement|Scalar $node) use ($native, $session): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile(), '?'));
                        $actual = (new Memory())->observe($session, $node, []);
                        return [serialize($expected), serialize($actual)];
                    }, $nodes);
                }, [[], ['CREATE DATABASE d', 'USE d'], []]);
                $trials = array_merge(...$observed);
                self::assertSame(array_column($trials, 0), array_column($trials, 1));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
