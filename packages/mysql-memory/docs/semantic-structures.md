# Constructing semantic structures directly

SQL Faker exercises the SQL-to-execution path. The direct structure campaign exercises a different input: objects built using SQL Semantics' public constructors, without first generating or parsing SQL. It tests whether the semantic model's types and invariants are sufficient for its consumer. It does not measure grammar coverage; SQL Faker owns that measure.

From `packages/mysql-memory` in a repository checkout:

```sh
composer test:property
composer fuzz:structures -- 100
composer fuzz:structures -- 100 example-seed
```

The campaign discovers concrete types from the reviewed semantic value inventories. Its generator interprets native constructor types and PHPDoc collection types, chooses enum cases, and builds fresh nested objects. A readonly field's declared `@var` type takes precedence over the corresponding constructor parameter: for example, a constructor may accept a broad list and check that every element is a select item before retaining it as a `non-empty-list<SelectExpression|Star|TableWildcard>`. Constructor invariant violations cause another construction attempt. The generator has finite depth and attempt budgets; exhaustion is reported as an incomplete trial and never as a successful execution. Primitive values and collection lengths are bounded, so passing a finite campaign is evidence about the sampled inputs.

The generated root is a MySQL statement or an ordinary expression placed in a SELECT item. The runner derives its semantic facts against a fresh in-memory session and sends the result directly to statement execution. The generator has no list of supported SQL commands and does not use execution success or semantic diagnostics to select inputs.

Every failure record includes its seed index, concrete root class, exception, and constructor rejection counts. To replay one trial from the same revision, give its index as the final argument:

```sh
php fuzz/structures.php 1 example-seed - 8.4.7 22
```

The arguments are `COUNT [SEED] [CLASS|-] [RELEASE] [START]`. Omitting the seed chooses a random seed and prints it before the first trial. An explicit class selects a root from the same discovered inventory. Reproduction requires the same revision because changes to types and constructors change deterministic choices.

Exit status 0 requires every trial to execute successfully. Status 1 reports an execution or structural failure. Status 2 reports an incomplete generation campaign, including an exhausted budget or unsupported constructor metadata. SQL errors are recorded separately, but currently also prevent success: the runner does not yet establish whether an individual error depends on execution state. A missing table and a state-independent model defect must not silently become the same successful outcome.

The unrestricted campaign currently finds counterexamples. The `property` test suite contains bounded regression properties for repaired model domains, including parameter binding across nested scopes and decoded strings across SQL escape modes; its success does not establish the property for the whole semantic model. Known outstanding categories include release-dependent forms, literal-only positions, and structures that produce state-independent SQL errors. These failures remain visible in the campaign until the corresponding type or invariant is addressed.
