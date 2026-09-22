# sql-catalog fuzz targets

Install the package's locked development dependencies with `composer install`.
Run from `packages/sql-catalog`:

```sh
composer fuzz:analyze -- --max-runs=10000
composer fuzz:recover -- --max-runs=10000
```

`composer fuzz` runs both with a bounded smoke budget. The commands create empty
corpus directories when needed; PHP-Fuzzer owns input mutation and the ignored
`fuzz/corpus/` contents.

## analyze

Feeds arbitrary bytes to the analyzer as if they were a source file. The analyzer
is pointed at whatever a repository happens to contain, so it has to survive
anything: a file that is not PHP, a file that is half PHP, a file that nests
deeper than anyone would write. Nothing it is given may make it throw — a file it
cannot read is reported as a problem, not raised — and the catalog it produces has
to be coherent with itself.

## recover

Generates a statement with sql-faker's MySQL grammar through the `BytePlanCompiler`,
then writes it into PHP through a construction the same input picks: one literal,
a concatenation of two halves, a global constant, a class constant, a variable, or
a function's return value. The catalog has to hold exactly that statement back,
character for character. This is the property the whole analysis rests on: how a
query is assembled must not change what it is.

`MYSQL_VERSION` selects the grammar (default `mysql-8.4.7`) and `MAX_EXPANSIONS`
bounds expansion (default 64).

Both targets reject all exceptions at the PHP-Fuzzer boundary. A finding includes
the raw input; the workflow uploads the campaign output and fails when the output
reports a crash.
