# How it is verified

The analyzer is checked against the contracts its design rests on, not against a
collection of PHP snippets that happen to have been thought of. Each contract is
a property that must hold whatever the input, and each has tests that fail when
it does not.

## The contracts

| Contract | What must hold |
|----------|----------------|
| **Finding the call** | A call that receives SQL is reported even when its statement cannot be recovered, even when the budget stops before anything is read from it, and even when the analysis could not tell what it was called on. A call the analysis *did* tell apart is not reported, so the report says "gap" only where there is one. |
| **Covering the dependencies** | A definition, branch or caller that can change the SQL is not skipped. |
| **Keeping the correspondence** | Values decided together stay together. Where the analyzer cannot establish that, it says so rather than presenting invented combinations as fact. |
| **Recovering faithfully** | Substitution, concatenation and generalization do not change what the statement says. |
| **Judging completion honestly** | A search that did not close is not reported as one that did. |

## Where each is checked

### Finding the call

`tests/Unit/AnalyzerTest.php` holds the three cases that can lose one:

- a call whose SQL argument never resolves is still reported, with its statement open;
- a call the budget stops before anything is read from it is reported as
  `not-analyzed`, with `searchClosed` false and a `call-not-analyzed` finding;
- a call written in an operand nothing needs the value of, `$on && $pdo->query(…)`,
  is found.

`SinkFinder` collects such calls independently of evaluation, and every one of
them is a starting point: the analysis begins at the call, so there is no walk
that could pass it by. A call nothing could be read from is recorded with its
statement left open rather than dropped. `AnalyzerTest` also pins the other side
of the contract: a call on a class the enabled extensions do not name is left out
of the catalog entirely, because naming it is an answer and not a gap.

### Covering the dependencies

The walk back from a call keeps what the SQL depends on and nothing else, and
each place a dependency can hide has a test of its own:

- `BackwardSlicerTest` — an assignment the argument reads is kept, one it does
  not read is stepped over, an append keeps the walk looking for what it added
  to, and a closure takes what it captured from where it is written;
- `BranchArmsTest` — every arm of an `if`, `switch` and `try` is a way through,
  including the empty arm taken when no condition holds, and only an arm that
  cannot fall through is left out;
- `LoopPassesTest` — a loop is taken for every number of passes up to the limit,
  a `foreach` over a written-out array exactly as often as it has elements, and a
  loop that could go round again is marked as cut short;
- `CallersTest` and `EntryBinderTest` — a parameter is looked for at every call
  of its body, a method call is only taken as one when its receiver can be of the
  body's class and `parent::`/`self::`/`static::` dispatch as PHP dispatches them,
  a call that cannot be confirmed marks the ways in as cut short rather than
  contributing arguments, and a body nothing calls leaves the parameter open;
- `PropertyWritesTest` — a property of `$this` takes what every method that
  writes it leaves, its promoted constructor parameter and its default;
- `CalleeReturnsTest` and `CallEvaluatorTest` — calls into the analyzed source
  are read at every `return`, dispatch resolves across the implementations the
  source declares, and recursion and the depth limit stop with `budget` rather
  than silently.

### Cross-checking the call paths against peq

The callers a parameter is looked for at are the analyzer's own call graph, and
a wrong edge in it would let an unrelated call pass its arguments in. That graph
is checked against [peq](https://github.com/k-kinzal/peq), which builds a PHP
dependency graph independently:

```bash
peq graph 'MATCH (a)-[e:`call`]->(b) RETURN a.id, a.kind, b.id, b.kind, e.kind, e.file, e.line' \
    <dir> --type=native --output=json
```

Every function and method in the analyzed files is asked for its callers
through `Callers::of()`, and the pairs are compared with peq's. On the 93
WordPress files that name `$wpdb`:

| | Pairs |
|---|---|
| Found by both | 8067 |
| Found only by peq | 92 — every one a call inside `wpdb` on itself, which the analyzer deliberately does not climb (an extension models the class) |
| Found only by the analyzer | 265 — 126 receivers typed from a global, a `new` or a typed property; 67 `$this->` calls landing in a subclass override or an inherited method; 72 `new` reaching a constructor. peq's native analyser does not infer any of the three; each was checked by hand on a sample |

The first runs of this comparison found four defects, all since fixed and
pinned by `CallersTest`: a call on an untyped receiver was taken as a caller of
any method of that name no other analyzed class declared, which attributed
`DateTime::format()` and methods of classes outside the analyzed files to
unrelated methods; `parent::` and `self::` were read as `static::`, reaching
overrides they cannot reach; `$this->m()` in a class that overrides `m` was also
taken as a call of the parent's `m`; and a constructor's `parent::__construct()`
callers were missed. None of them changed a statement WordPress issues, but each
could have on other code.

The `through` path of every catalogued statement is checked the same way: of
its 209 hops on WordPress, 205 are edges peq also finds, 2 are typed-receiver
edges from the table above, and 2 start at file scope, which peq has no node
for.

### Keeping the correspondence

`AnalyzerTest` asserts the exact set of statements, so both a missing one and an
invented one fail:

- the branch that assigns a table and a column produces exactly two statements;
- nested branches produce exactly the combinations their arms can reach;
- two independent branches produce exactly their four combinations;
- statements paired from parts that vary independently are marked
  `correlated: false`.

### Recovering faithfully

The `recover` fuzz target generates a statement with sql-faker's MySQL grammar,
writes it into PHP through a construction the same input picks — one literal, a
concatenation of two halves, a global constant, a class constant, a variable, a
function's return value — and requires the catalog to hold that statement back,
character for character. This is the property the whole analysis rests on: how a
query is assembled must not change what it is.

The `analyze` target feeds arbitrary bytes to the analyzer as a source file.
Nothing may make it throw; a file it cannot read is reported, not raised.

### Judging completion honestly

`ResolutionTest` covers the classification of every origin, including that a
search stopped by a budget outranks one that reached runtime input — the first
says the analyzer did not finish, the second says the program's own string is not
fixed.

`SliceExecutorTest` checks the other half of that: when a variable takes
several values the run splits, so a value read twice is the same value both
times, and runs past the limit are joined rather than dropped.

`EntryFactoryTest` checks that a resolved reading of a call does not delete an
unresolved one, that a statement stopped by a budget is reported as
`analysis-incomplete` rather than as merely dynamic, that a statement a bound on
loop passes or callers cut short says so even when its text resolved, and that a
call no statement was read from says which of the two reasons applies — the
analysis stopped before reading it, or could not tell what it was called on.

`CatalogEntryTest` pins `searchClosed` to both conditions: a resolution that
closed, and no bound that cut the search short. `SliceExecutorTest` checks that a
run the budget stops leaves every name a step it did not get to would have
written open, so stopping never reports a value a later assignment replaces.

`ResolutionTest` also covers `wasRead()`, which is what keeps the two apart in
the reports: a gap left by the analyzer stopping is not a value spliced into a
statement, and must not be reported as one.

## The rest of the gates

- **Pairing.** Every class in `src/` has a test in `tests/Unit/`, and every public
  method has a test method named after it. Both are enforced by PHPStan rules.
- **Documented examples.** Every declaration marked `@visibility public` carries a
  runnable `@example`, executed as a test by `composer doctest`.
- **The catalog format.** `JsonReporterTest` validates a rendered document against
  the JSON Schema the package ships, so the schema cannot drift from the reporter.
- **Determinism.** The JSON document is asserted byte for byte, so nothing that
  varies between runs can creep into an artifact meant to be diffed.

## What is deliberately not claimed

Running a corpus and comparing what a driver saw with what the analyzer said
would measure one sample of PHP, not the contracts. It answers "did these files
work", which is a weaker question than "can this property be broken", and it
invites tuning the analyzer to the corpus. The contracts above are what the
analysis is expected to hold to; the fuzz targets are what look for inputs that
break them.
