# Runnable examples

Install the package dependencies in this checkout, then run:

```sh
php examples/symbolic-return.php
php examples/reference-model.php
php examples/builder-state.php
```

- `symbolic-return.php` emits a JSON result retaining a symbolic parameter.
- `reference-model.php` prints `[3,3]`: a modeled invocation calls source code that updates a shared reference.
- `builder-state.php` prints `admins`: an alias updates abstract object state, observed directly at a call site.

Each application snippet is captured source data. Running these examples executes the analyzer and explicitly installed model code, never the captured application.
