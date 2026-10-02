<?php

declare(strict_types=1);

namespace Tests\ModelContract;

use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Query\QueryScope;
use Deriver\Query\ValueQuery;
use Deriver\Result\Alternative;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\RouteModels;

/**
 * User models run callbacks that framework APIs without source would invoke.
 */
#[CoversNothing]
#[Small]
final class FrameworkCallbackContractTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testModelInvokesARouteCallbackOnATypedFrameworkReceiver(): void
    {
        $session = Analysis::session('<?php function routes(Slim\App $app, PDO $pdo, string $table) { $app->get("/", function () use ($pdo, $table) { $pdo->query("SELECT * FROM $table"); }); }', new Configuration(models: [RouteModels::get()]));
        $scope = QueryScope::fromEntrypoints([new EntryPoint('routes', [Term::parameter('app', 'Slim\App'), Term::parameter('pdo', 'PDO'), Term::constant('users')])]);
        [$sql, $receiver] = RouteModels::observe($session, $scope);
        self::assertSame(['SELECT * FROM users'], array_map(static fn (Alternative $outcome) => $outcome->values['value']->native(), $sql));
        self::assertSame([['parameter', 'PDO']], array_map(static fn (Alternative $outcome): array => [$outcome->values['value']->kind, $outcome->values['value']->attributes['type'] ?? ''], $receiver));
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testModelInvokesARouteCallbackOnAFrameworkObjectCreatedInTheEntrypoint(): void
    {
        $session = Analysis::session('<?php function routes(PDO $pdo) { $table = "users"; $app = new Slim\App(); $app->get("/", function () use ($pdo, $table) { $pdo->query("SELECT * FROM $table"); }); }', new Configuration(models: [RouteModels::get(), RouteModels::construct()]));
        [$sql, $receiver] = RouteModels::observe($session, QueryScope::fromEntrypoints([new EntryPoint('routes', [Term::parameter('pdo', 'PDO')])]));
        self::assertSame(['SELECT * FROM users'], array_map(static fn (Alternative $outcome) => $outcome->values['value']->native(), $sql));
        self::assertSame([['parameter', 'PDO']], array_map(static fn (Alternative $outcome): array => [$outcome->values['value']->kind, $outcome->values['value']->attributes['type'] ?? ''], $receiver));
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testUnmodeledFrameworkCallbacksStayAnExplicitOpenDispatch(): void
    {
        $session = Analysis::session('<?php function routes(Slim\App $app, PDO $pdo) { $app->get("/", function () use ($pdo) { $pdo->query("SELECT 1"); }); }');
        $result = $session->derive(new ValueQuery($session->callsTo('query')[0]->argument(0), scope: QueryScope::fromEntrypoints([new EntryPoint('routes', [Term::parameter('app', 'Slim\App'), Term::parameter('pdo', 'PDO')])])));
        self::assertContains('OPEN_DISPATCH', array_column($result->frontiers, 'code'));
        self::assertNotContains('SELECT 1', array_map(static fn (Alternative $outcome) => ($outcome->values['value'] ?? null)?->native(), $result->normalOutcomes));
    }
}
