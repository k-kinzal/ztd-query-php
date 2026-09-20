# How it is verified

The analyzer is checked against the contracts its design rests on, not against a
collection of PHP snippets that happen to have been thought of. Each contract is
a property that must hold whatever the input, and each has tests that fail when
it does not.

## The contracts

| Contract | What must hold |
|----------|----------------|
| **Finding the call** | A call that receives SQL is reported even when its statement cannot be recovered, even when the walk never reached it, and even when the walk could not tell what it was called on. A call the walk *did* tell apart is not reported, so the report says "gap" only where there is one. |
| **Covering the dependencies** | A definition, branch or caller that can change the SQL is not skipped. |
| **Keeping the correspondence** | Values decided together stay together. Where the analyzer cannot establish that, it says so rather than presenting invented combinations as fact. |
| **Recovering faithfully** | Substitution, concatenation and generalization do not change what the statement says. |
| **Judging completion honestly** | A search that did not close is not reported as one that did. |

## Where each is checked

### Finding the call

`tests/Unit/AnalyzerTest.php` holds the three cases that can lose one:

- a call whose SQL argument never resolves is still reported, with its statement open;
- a call the walk could not reach — budget exhausted before it — is reported as
  `not-analyzed`, with `searchClosed` false and a `call-not-analyzed` finding;
- a call written in an operand nothing needs the value of, `$on && $pdo->query(…)`,
  is found.

`SinkFinder` collects such calls independently of evaluation, and
`StatementRecorder` records both which calls the walk reached and which it could
explain, so the second case is answered by comparing the three rather than by
hoping. `AnalyzerTest` also pins the other side of the contract: a call on a
class the enabled extensions do not name is left out of the catalog entirely,
because naming it is an answer and not a gap.

### Covering the dependencies

`SinkFinderTest` checks that reaching spreads backwards over the call graph: a
function that issues a statement, and a function that only calls it, are both
kept; an unrelated function is not. Over-approximating keeps bodies in — the
failure mode is wasted work, not a missed statement.

`CallEvaluatorTest` checks that calls into the analyzed source are followed, that
dispatch resolves across the implementations the source declares, and that
recursion and the depth limit stop with `budget` rather than silently.

### Keeping the correspondence

`AnalyzerTest` asserts the exact set of statements, so both a missing one and an
invented one fail:

- the branch that assigns a table and a column produces exactly two statements;
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

`EntryFactoryTest` checks that a resolved reading of a call does not delete an
unresolved one, that a statement stopped by a budget is reported as
`analysis-incomplete` rather than as merely dynamic, and that a call no statement
was read from says which of the two reasons applies — never reached, or reached
and not identifiable.

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
