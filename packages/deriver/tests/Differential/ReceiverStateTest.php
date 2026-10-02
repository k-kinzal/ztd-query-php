<?php

declare(strict_types=1);

namespace Tests\Differential;

use Deriver\Project\EntryPoint;
use Deriver\Query\QueryScope;
use Deriver\Query\ReturnQuery;
use Deriver\Result\Alternative;
use Deriver\Result\Exceptional;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fake\Analysis;
use Tests\Fake\RuntimeOracle;

/**
 * Checks that every way PHP can hand an object to an entry is among the derived candidates.
 */
#[CoversNothing]
#[Medium]
final class ReceiverStateTest extends TestCase
{
    private const CLASSES = '<?php final class Conn{public function query(string $sql):string{return "ran:".$sql;}}'
        . 'final class Repo{private string $order="name";public function __construct(private Conn $conn){}public function run(string $sql):string{return $this->conn->query($sql." ORDER BY ".$this->order);}}'
        . 'function viaParameter(Repo $r){return $r->run("SELECT 1");}';

    /**
     * @param string $target Runtime construction of the supplied object
     * @param EntryPoint $entry Analyzed entry describing the same object
     * @throws JsonException If fixture observations cannot be encoded
     * @throws RuntimeException If the PHP 8.3 oracle is unavailable
     */
    #[DataProvider('objects')]
    public function testRuntimeOutcomeIsAmongDerivedCandidates(string $target, EntryPoint $entry): void
    {
        $source = self::CLASSES . $target;
        $runtime = RuntimeOracle::observe($source);
        $result = Analysis::session($source)->derive(new ReturnQuery($entry->symbol, QueryScope::fromEntrypoints([$entry])));
        if ($runtime['exception'] !== '') {
            self::assertContains($runtime['exception'], array_map(static fn (Exceptional $outcome) => $outcome->exception->literal, $result->exceptionalOutcomes));
        } else {
            self::assertContains($runtime['value']->native(), array_map(static fn (Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes));
        }
    }

    /**
     * @return array<string, array{string, EntryPoint}> Runtime constructions and the matching analyzed entries
     */
    public static function objects(): array
    {
        $parameter = new EntryPoint('viaParameter', [Term::parameter('r', 'Repo')]);
        $bare = 'function bare(){return (new ReflectionClass(Repo::class))->newInstanceWithoutConstructor();}';
        return [
            'argument created without its constructor' => [$bare . 'function target(){return viaParameter(bare());}', $parameter],
            'receiver created without its constructor' => [$bare . 'function target(){return bare()->run("SELECT 2");}', new EntryPoint('Repo::run', ['sql' => Term::constant('SELECT 2')])],
        ];
    }
}
