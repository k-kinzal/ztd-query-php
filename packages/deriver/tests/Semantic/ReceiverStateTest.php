<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Project\EntryPoint;
use Deriver\Query\QueryScope;
use Deriver\Query\ReturnQuery;
use Deriver\Query\ValueQuery;
use Deriver\Result\Alternative;
use Deriver\Result\Exceptional;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

/**
 * Objects supplied from outside the analysis keep their declared property types.
 */
#[CoversNothing]
#[Medium]
final class ReceiverStateTest extends TestCase
{
    private const LAYERS = '<?php final class Conn{public function query(string $sql):string{return "ran:".$sql;}}'
        . 'final class Repo{public function __construct(private Conn $conn){}public function run(string $sql):string{return $this->conn->query($sql);}}'
        . 'final class Ctl{public function __construct(private Repo $repo){}public function list():string{return $this->repo->run("SELECT 2");}}'
        . 'function viaParameter(Repo $r){return $r->run("SELECT 1");}function viaChain(Ctl $c){return $c->list();}';

    private const PDO_LAYERS = '<?php final class Repo{public function __construct(private PDO $pdo){}public function run(string $sql){return $this->pdo->query($sql);}}'
        . 'final class Ctl{public function __construct(private Repo $repo){}public function list(){return $this->repo->run("SELECT 2");}}'
        . 'function viaParameter(Repo $r){return $r->run("SELECT 1");}function viaChain(Ctl $c){return $c->list();}';

    /**
     * A constructed object reaches the method body; an object created without its constructor makes the read throw Error.
     * @param string $symbol Entry callable
     * @param list<Term> $arguments Symbolic objects supplied by the caller
     * @param string $expected PHP result for an object built by its constructor
     * @throws JsonException If captured fixture values cannot be encoded
     */
    #[DataProvider('suppliedObjects')]
    public function testSuppliedObjectsReadTheirDeclaredPropertyTypes(string $symbol, array $arguments, string $expected): void
    {
        $session = Analysis::session(self::LAYERS);
        $entry = $session->derive(new ReturnQuery($symbol, QueryScope::fromEntrypoints([new EntryPoint($symbol, $arguments)])));
        $symbolic = $session->derive(new ReturnQuery($symbol));
        foreach ([$entry, $symbolic] as $result) {
            self::assertEqualsCanonicalizing([$expected], array_values(array_unique(array_map(static fn (Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes), SORT_REGULAR)));
            self::assertSame([], $result->frontiers);
            self::assertNotEmpty($result->exceptionalOutcomes);
            self::assertSame(['Error'], array_values(array_unique(array_map(static fn (Exceptional $outcome) => $outcome->exception->literal, $result->exceptionalOutcomes))));
        }
        self::assertCount(count($symbolic->exceptionalOutcomes), $entry->exceptionalOutcomes);
    }

    /**
     * @return array<string, array{string, list<Term>, string}> Entries and the value PHP returns for constructed objects
     */
    public static function suppliedObjects(): array
    {
        return [
            'function parameter' => ['viaParameter', [Term::parameter('r', 'Repo')], 'ran:SELECT 1'],
            'controller to repository to connection' => ['viaChain', [Term::parameter('c', 'Ctl')], 'ran:SELECT 2'],
            'controller receiver' => ['Ctl::list', [], 'ran:SELECT 2'],
        ];
    }

    /**
     * @param string $symbol Entry callable
     * @param list<Term> $arguments Symbolic objects supplied by the caller
     * @param string $sql Statement passed to PDO::query
     * @throws JsonException If captured fixture values cannot be encoded
     */
    #[DataProvider('pdoEntries')]
    public function testPdoReceiverReachedThroughSuppliedObjectsKeepsItsType(string $symbol, array $arguments, string $sql): void
    {
        $session = Analysis::session(self::PDO_LAYERS);
        $call = $session->callsTo('query')[0];
        $scope = QueryScope::fromEntrypoints([new EntryPoint($symbol, $arguments)]);
        $statement = $session->derive(new ValueQuery($call->argument(0), scope: $scope));
        self::assertEqualsCanonicalizing([$sql], array_values(array_unique(array_map(static fn (Alternative $outcome) => $outcome->values['value']->native(), $statement->normalOutcomes), SORT_REGULAR)));
        self::assertNotNull($call->receiver);
        $receiver = $session->derive(new ValueQuery($call->receiver, scope: $scope));
        self::assertNotEmpty($receiver->normalOutcomes);
        foreach ($receiver->normalOutcomes as $outcome) {
            self::assertSame('PDO', $outcome->values['value']->attributes['type'] ?? null);
            self::assertSame('state', $outcome->values['value']->attributes['stability'] ?? null);
        }
    }

    /**
     * @return array<string, array{string, list<Term>, string}> Entries reaching PDO::query
     */
    public static function pdoEntries(): array
    {
        return [
            'function parameter' => ['viaParameter', [Term::parameter('r', 'Repo')], 'SELECT 1'],
            'controller to repository to PDO' => ['viaChain', [Term::parameter('c', 'Ctl')], 'SELECT 2'],
        ];
    }

    /**
     * Two typed residuals may be different objects, so a write through one is not a read through the other.
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testUnrelatedInvalidatedObjectsDoNotShareState(): void
    {
        $result = Analysis::returns('<?php final class Box{public int $v=0;public function set(int $v):void{$this->v=$v;}public function get():int{return $this->v;}}'
            . 'final class Holder{public Box $a;public Box $b;}function target(Holder $h){$h->a;$h->b;$k=$h;unknown_effect($k);$h->a->set(1);return $h->b->get();}');
        self::assertNotEmpty($result->normalOutcomes);
        foreach ($result->normalOutcomes as $outcome) {
            self::assertFalse($outcome->values['return']->isConcrete());
            self::assertSame('int', $outcome->values['return']->attributes['type'] ?? null);
        }
    }
}
