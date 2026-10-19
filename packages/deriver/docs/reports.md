# Reports

Reports go to stdout and errors to stderr. Choose `--format=text` for terminal output or `--format=json` for other tools.

## Text

The [command line example](cli.md#example) produces:

```text
[0] analyzed int
42
Evidence: 1 derivation(s).
```

Each candidate shows its index, status, PHP type, value, and number of supporting derivations.

| Status | Meaning |
|--------|---------|
| `analyzed` | A concrete value was derived. |
| `partials` | Some inputs or operations remain unresolved. The value shows the remaining expression and its dependencies. |

`No candidates.` means the query produced no values. An interruption message means exploration stopped before completion; the command exits with `1`. Partial candidates and empty results alone do not cause failure. See [exit codes](cli.md#exit-codes).

## JSON

```console
vendor/bin/deliver 'App\answer#return' --path=src --format=json > report.json
```

The top-level fields are `interrupted` and `candidates`. Check `interrupted` before treating the collection as complete.

| Candidate field | Meaning |
|-----------------|---------|
| `type` | `analyzed` or `partials`. |
| `type_name` | Known PHP type, or `mixed`. |
| `result` | Concrete JSON value, or an unresolved expression represented by `root` and `nodes`. |
| `evidence` | Supporting derivations, each listed as nodes with identifiers, kinds, inputs, source locations, and attributes. |

For a partial result, `root` indexes the starting node in `nodes`; each node's `operands` refers to other indexes in the same list. In evidence, `inputs` refers to node identifiers within that derivation.

Invalid UTF-8 strings and non-finite floats cannot be reported as JSON. Use text output to inspect these values.

## Unresolved values

Code outside the selected files, unknown external inputs, and unsupported operations can leave partial results. Include relevant source directories with `--path` and supply known inputs through [configuration](configuration.md). Parsing a PHP construct does not guarantee that its value can be derived; include/require, generators, and dynamic property overloading are among the unsupported cases.

A method call whose receiver class cannot be determined, such as a call on an untyped parameter or property, is still a possible call of every method with that name. Its argument is reported as a partial whose reason is `UNRESOLVED_DISPATCH`, with the derived argument value inside it. A call to code outside the selected files is unrelated to globals and statics it was not given through its receiver, arguments, or references, so they keep their values across the call. A function local read before any assignment is `null`, unless the body uses variable variables, include, eval, or `extract()`.
