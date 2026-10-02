<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Exception\InvalidInputException;
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
 * Objects supplied from outside the analysis keep their declared property types, and entries can supply their state.
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

    private const REPOSITORY = '<?php final class UserRepository{private const TABLE="users";private string $order="name";'
        . 'public function __construct(private PDO $pdo){}'
        . 'public function findByStatus(string $status):string{return "SELECT id FROM ".self::TABLE." WHERE status = :status ORDER BY ".$this->order;}}';

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

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testEachEntryContributesItsReceiverPropertyCandidate(): void
    {
        $session = Analysis::session(self::REPOSITORY);
        $default = $session->declarations()->class('UserRepository')?->properties['order']->default;
        self::assertNotNull($default);
        $status = [Term::parameter('status', 'string')];
        $result = $session->derive(new ReturnQuery('UserRepository::findByStatus', QueryScope::fromEntrypoints([
            new EntryPoint('UserRepository::findByStatus', $status, properties: ['order' => $default]),
            new EntryPoint('UserRepository::findByStatus', $status, properties: ['order' => Term::constant('email')]),
        ])));
        self::assertEqualsCanonicalizing(['SELECT id FROM users WHERE status = :status ORDER BY email', 'SELECT id FROM users WHERE status = :status ORDER BY name'], array_values(array_unique(array_map(static fn (Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes), SORT_REGULAR)));
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertSame([], $result->frontiers);
    }

    /**
     * Unsupplied properties stay symbolic values of their declared types; supplied ones are initialized.
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testSuppliedPropertiesAreInitializedAndOthersStaySymbolic(): void
    {
        $session = Analysis::session(self::LAYERS);
        $symbol = 'Repo::run';
        $absent = $session->derive(new ReturnQuery($symbol, QueryScope::fromEntrypoints([new EntryPoint($symbol, ['sql' => Term::constant('SELECT 3')])])));
        self::assertEqualsCanonicalizing(['ran:SELECT 3'], array_values(array_unique(array_map(static fn (Alternative $outcome) => $outcome->values['return']->native(), $absent->normalOutcomes), SORT_REGULAR)));
        self::assertSame(['Error'], array_values(array_unique(array_map(static fn (Exceptional $outcome) => $outcome->exception->literal, $absent->exceptionalOutcomes))));
        $supplied = $session->derive(new ReturnQuery($symbol, QueryScope::fromEntrypoints([new EntryPoint($symbol, ['sql' => Term::constant('SELECT 3')], properties: ['conn' => Term::parameter('conn', 'Conn')])])));
        self::assertEqualsCanonicalizing(['ran:SELECT 3'], array_values(array_unique(array_map(static fn (Alternative $outcome) => $outcome->values['return']->native(), $supplied->normalOutcomes), SORT_REGULAR)));
        self::assertSame([], $supplied->exceptionalOutcomes);
    }

    /**
     * PHP forbids modifying an initialized readonly property, including from its declaring class.
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testSuppliedReadonlyPropertyIsInitialized(): void
    {
        $session = Analysis::session('<?php final class Box{public function __construct(public readonly string $name){}public function rename():string{$this->name="other";return $this->name;}}');
        $result = $session->derive(new ReturnQuery('Box::rename', QueryScope::fromEntrypoints([new EntryPoint('Box::rename', properties: ['name' => Term::constant('box')])])));
        self::assertSame([], $result->normalOutcomes);
        self::assertSame(['Error'], array_values(array_unique(array_map(static fn (Exceptional $outcome) => $outcome->exception->literal, $result->exceptionalOutcomes))));
    }

    /**
     * A property name resolves to the receiver's storage slot, including a private slot inherited from a parent class.
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testSuppliedPropertyReachesAnInheritedPrivateSlot(): void
    {
        $session = Analysis::session('<?php class Base{private string $table="users";protected function table():string{return $this->table;}}final class Child extends Base{public function sql():string{return "SELECT * FROM ".$this->table();}}');
        $result = $session->derive(new ReturnQuery('Child::sql', QueryScope::fromEntrypoints([new EntryPoint('Child::sql', properties: ['table' => Term::constant('posts')])])));
        self::assertEqualsCanonicalizing(['SELECT * FROM posts'], array_values(array_unique(array_map(static fn (Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes), SORT_REGULAR)));
    }

    /**
     * @param string $source PHP fixture
     * @param EntryPoint $entry Entry with properties that cannot describe a PHP object
     * @throws JsonException If captured fixture values cannot be encoded
     */
    #[DataProvider('invalidProperties')]
    public function testRejectsPropertiesNoPhpObjectCanHave(string $source, EntryPoint $entry): void
    {
        $session = Analysis::session($source);
        $this->expectException(InvalidInputException::class);
        $session->derive(new ReturnQuery($entry->symbol, QueryScope::fromEntrypoints([$entry])));
    }

    /**
     * @return array<string, array{string, EntryPoint}> Entries whose supplied properties are rejected
     */
    public static function invalidProperties(): array
    {
        $box = '<?php declare(strict_types=1); final class Box{private string $name="box";public static int $count=0;public function name():string{return $this->name;}public static function make():string{return "x";}}function free():int{return 1;}';
        return [
            'static method' => [$box, new EntryPoint('Box::make', properties: ['name' => Term::constant('x')])],
            'function' => [$box, new EntryPoint('free', properties: ['name' => Term::constant('x')])],
            'undeclared property' => [$box, new EntryPoint('Box::name', properties: ['missing' => Term::constant('x')])],
            'static property' => [$box, new EntryPoint('Box::name', properties: ['count' => Term::constant(1)])],
            'declared type violation' => [$box, new EntryPoint('Box::name', properties: ['name' => Term::constant(1)])],
        ];
    }

    /**
     * @param array<string, Term> $properties Malformed property map
     */
    #[DataProvider('malformedNames')]
    public function testRejectsNamesThatAreNotPropertyNames(array $properties): void
    {
        $this->expectException(InvalidInputException::class);
        new EntryPoint('Box::name', properties: $properties);
    }

    /**
     * @return array<string, array{array<int|string, Term>}> Keys PHP cannot use as property names
     */
    public static function malformedNames(): array
    {
        return ['integer key' => [[0 => Term::constant('x')]], 'empty' => [['' => Term::constant('x')]], 'sigil' => [['$name' => Term::constant('x')]], 'leading digit' => [['1name' => Term::constant('x')]]];
    }
}
