<?php

declare(strict_types=1);

namespace Deriver\Evaluation;

use Deriver\Evaluation\Control\Handler;
use Deriver\Evaluation\Control\IteratorCursor;
use Deriver\Memory\Location;
use Deriver\Memory\Memory;
use Deriver\Value\Term;

/**
 * One correlated execution path; forks copy state without sharing mutable memory.
 * @visibility root
 */
final class State
{
    /**
     * @var array<string, Term> SSA expression results.
     */
    public array $registers = [];
    /**
     * @var array<string, string> Register definitions in the derivation graph.
     */
    public array $producers = [];
    /**
     * @var array<string, Location> Address registers.
     */
    public array $addresses = [];
    /**
     * @var array<string, Offset\Address> Raw offset chains retained until their storage operation.
     */
    public array $offsets = [];
    /**
     * @var array<string, Call\Preparation\Target> Signatures captured before evaluating arguments.
     */
    public array $callTargets = [];
    /**
     * @var array<string, Transfer\PropertySlot> Property metadata by address register.
     */
    public array $properties = [];
    /**
     * @var array<string, Location> Local variable bindings.
     */
    public array $locals = [];
    /**
     * @var array<string, bool> Correlated branch decisions.
     */
    public array $guard = [];
    /**
     * @var array<string, array{min: int|float|null, max: int|float|null, equal: Term|null, excluded: list<Term>}> Input constraints.
     */
    public array $constraints = [];
    /**
     * @var list<Handler> Active exception regions.
     */
    public array $handlers = [];
    /**
     * @var array<string, IteratorCursor> Active foreach cursors.
     */
    public array $iterators = [];
    /**
     * @var array<int, int> Loop header visits.
     */
    public array $visits = [];
    /**
     * @var list<string> Derivation node identifiers.
     */
    public array $evidence = [];
    /**
     * @var list<string> Definitions that determine this path's reachability.
     */
    public array $controls = [];
    /**
     * @var array<int, array<string, Term>> Loop entry storage.
     */
    public array $loopEntries = [];
    /**
     * Current CFG block.
     */
    public int $block = 0;
    /**
     * Previously completed block for Phi selection.
     */
    public int $previous = -1;
    /**
     * Current completion.
     */
    public Completion $completion;
    /**
     * Whether this execution path has reached the query's observation point.
     */
    public bool $observed = false;
    /**
     * Cause of unknown writes to this frame's symbol table, including future bindings.
     */
    public ?string $unknownLocals = null;
    /**
     * Late static binding class.
     */
    public string $lateStaticClass = '';

    /**
     * @var array<int, array<string, Term>> Widened loop header approximations.
     */
    public array $approximations = [];
    /**
     * @var array<int, string> Alias and iterator structure at a widened header.
     */
    public array $loopStructures = [];
    /**
     * @var array<int, array<string, bool>> Guards established outside each loop.
     */
    public array $loopGuards = [];
    /**
     * Loop header whose body has reached a checked post-fixpoint.
     */
    public ?int $stableHeader = null;

    /**
     * @param Memory $memory Path-specific memory
     */
    public function __construct(public Memory $memory = new Memory())
    {
        $this->completion = new Completion();
    }

    /**
     * Forks a correlated path with independent subsequent writes.
     * @return self Forked state
     */
    public function fork(): self
    {
        $fork = clone $this;
        $fork->memory = clone $this->memory;
        return $fork;
    }

    /**
     * Resolves a local binding, allocating an uninitialized cell when needed.
     * @param string $name Variable name
     * @return Location Address with its local binding identity
     */
    public function local(string $name): Location
    {
        if (!isset($this->locals[$name])) {
            $initial = in_array($name, ['_GET', '_POST', '_COOKIE', '_SERVER', '_ENV', '_REQUEST', '_FILES', '_SESSION'], true) ? new Term('external', 'superglobal:' . $name, attributes: ['type' => 'array', 'stability' => 'request']) : new Term('uninitialized');
            if ($this->unknownLocals !== null) {
                $initial = Term::opaque($this->unknownLocals);
            }
            $this->locals[$name] = $this->memory->allocate($initial);
        }
        $location = $this->locals[$name];
        return new Location($location->root, $location->path, $name, $location->unknown);
    }

    /**
     * Rebinds a PHP reference without retargeting other aliases of the old cell.
     * @param Location $destination Left address
     * @param Location $source Right address
     */
    public function alias(Location $destination, Location $source): void
    {
        if ($source->unknown) {
            $this->unknownAlias($destination, $source);
            return;
        }
        $cell = $this->memory->reference($source);
        if ($destination->local !== '' && $destination->path === []) {
            if (str_starts_with($destination->root, 'global:') && $destination->root !== $cell) {
                $this->memory->writePath($destination->root, [], new Term('cell', $cell), true);
            }
            $this->locals[$destination->local] = new Location($cell);
            return;
        }
        $this->memory->writePath($destination->root, $destination->path, new Term('cell', $cell), true);
    }

    /**
     * Binds a reference to an unknown slot of known storage so that writes through either side invalidate that storage.
     * @param Location $destination Left address
     * @param Location $source Unknown slot, whose root is the storage it may belong to
     */
    public function unknownAlias(Location $destination, Location $source): void
    {
        if ($destination->local !== '' && $destination->path === [] && !str_starts_with($destination->root, 'global:')) {
            $this->locals[$destination->local] = new Location($source->root, $source->path, $destination->local, true);
            return;
        }
        $this->memory->write($source, Term::opaque('UNKNOWN_REFERENCE'));
        $this->memory->write($destination, Term::opaque('UNKNOWN_REFERENCE'));
    }

    /**
     * Returns a register while keeping absent computation distinct from null.
     * @param string $register SSA register
     * @return Term Available value or an explicit residual
     */
    public function value(string $register): Term
    {
        return $this->memory->dereference($this->registers[$register] ?? Term::opaque('UNCOMPUTED_REGISTER'));
    }

    /**
     * Produces the observable local state.
     * @return array<string, Term> Materialized local values
     */
    public function snapshot(): array
    {
        $values = [];
        foreach ($this->locals as $name => $location) {
            $values[$name] = $this->memory->materialize($this->memory->read($location));
        }
        ksort($values);
        return $values;
    }
}
