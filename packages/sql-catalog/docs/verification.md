# How it is verified

A static answer to "what SQL does this application issue" is only worth something
if it does not miss statements the application really issues. Saying so is not
enough, so the property is checked against a program that actually runs.

## The property

**Soundness.** Every statement a run sends to the driver is matched by some
catalogued statement, and every value the run binds is admitted by the domain the
catalog reports for that placeholder.

The analyzer is allowed to over-approximate — to report a statement a particular
run never reaches, or to report a shape with a gap where a statement is in fact
fixed. It is not allowed to under-approximate.

## Four independent checks

### 1. Unit tests

Every class in `src/` has a paired test in `tests/Unit/`, and every public method
has a test method named after it. Both pairings are enforced by PHPStan rules, so
neither can be skipped. `composer test:coverage` reports line coverage.

### 2. Documentation examples

Every declaration marked `@visibility public` carries an `@example`, and those
examples run as tests (`composer doctest`). A documented example that stops being
true is a failing test.

### 3. Conformance against a running program

`corpus/app/` holds small programs that issue SQL the way real code does: through
a repository with a constant table name, through an enum-typed parameter, through
a loop that appends filters, through `sprintf` and `implode`, through
`bindValue`, through an abstract base class, and through string interpolation of
request input.

`corpus/run.php` runs all of them against an in-memory SQLite database through a
`PDO` subclass that records every statement the driver is asked for and every
value bound to it, and prints the recording as JSON.

`tests/Conformance/` then analyzes the same directory statically and compares:

- every recorded statement has to match a catalogued one — `uncovered` must be empty;
- every recorded value has to be admitted by the reported domain — `valueMismatches` must be empty;
- the share of recorded statements matched by a fully resolved catalogue entry is
  asserted against a floor, so a regression in precision fails the build.

The comparison itself lives in `src/Conformance/` rather than in the test, which
makes it a capability of the package: a catalog can be checked against any
recording, including a production query log.

### 4. Fuzzing

Two targets, both run in CI on a schedule (`composer fuzz`):

- **`analyze`** feeds arbitrary bytes to the analyzer as if they were a source
  file. Nothing may make it throw; a file it cannot read is reported, not raised.
- **`recover`** generates a statement with sql-faker's MySQL grammar, writes it
  into PHP through a construction the input picks — one literal, a concatenation,
  a global constant, a class constant, a variable, a function's return value —
  and requires the catalog to hold that statement back, character for character.
  This is the property the whole analysis rests on: how a query is assembled must
  not change what it is.

## Measured on the corpus

Run `composer test:conformance` to reproduce.

| Measure | Result |
|---------|--------|
| Statements the corpus issues at runtime | 22 |
| Matched by a catalogued statement | 22 (100%) |
| Matched by a **fully resolved** catalogued statement | 18 (81.8%) |
| Recorded values rejected by the reported domain | 0 |
| Placeholders resolved to a concrete set of values | 12 of 14 bound |

The four statements not resolved exactly are the ones the corpus builds
dynamically on purpose: two where column names come from an array parameter, and
two where a value is interpolated into the statement text from `$_GET`. Both are
reported with their resolved parts intact and the gap marked, which is the
correct answer rather than a limitation.

## On the placeholder value domains

Reporting what a placeholder can be bound to was the part of this package most at
risk of being decorative. It is not: of the 14 placeholders the corpus catalogs,
13 carry a binding and 12 of those resolve to a concrete set of values rather
than a bare type, and no value the corpus actually bound was rejected.

The case that pays for the feature is the enum. A parameter typed `Status` used
as `$status->value` is reported as `'active'|'banned'` — the values the column can
hold — rather than `string`. That is what makes comparing the values a write
statement can store against the values a read statement can filter on a
mechanical check rather than a reading exercise.

The one placeholder that reports only its type does so correctly: it belongs to a
public method that any caller may pass any integer to, and the catalog says `int`
instead of the literal one caller happened to use. Narrowing there would be an
under-approximation, which is the one thing the analysis must not do.
