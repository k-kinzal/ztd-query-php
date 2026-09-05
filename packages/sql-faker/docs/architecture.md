# SQL generation architecture

Every dialect compiles to `SqlFaker\Grammar\Grammar`, containing the same
`ProductionRule`, `Production`, `Terminal` and `NonTerminal` types. The serialized
MySQL artifacts use this model too. No runtime conversion of a second AST is
needed by a Provider.

A public Provider resolves its release and creates a dialect `GenerationContext`.
That context prepares the common grammar, a `LexicalGrammar` implementation and,
where necessary, callbacks for parser semantics and version-specific start-rule
names. The Provider passes those inputs directly to
`SqlFaker\Generation\SqlGenerator` and passes a dialect `GenerationPlans` result
to each `generate()` call. The generator never selects a database implementation.

```mermaid
flowchart TD
    Provider --> Generation
    Provider --> Dialect[MySQL / PostgreSQL / SQLite]
    Generation --> Grammar[Common grammar and lexical contract]
    Dialect --> Grammar
    Compiler[Bison / Lemon compiler] --> Grammar
    Tooling[Artifact build orchestration] --> Dialect
    Tooling --> Grammar
    Compatibility --> Generation
    Compatibility --> Dialect
    Compatibility --> Compiler
```

| Layer | Responsibility | Allowed dependencies |
| --- | --- | --- |
| Provider | Stable Faker entry points and composition | Generation, Grammar, individual dialects, Faker |
| Generation | Derivation and lexical retries for any common AST | Grammar, Faker |
| Grammar | Shared symbol model, generation plans, termination analysis, lexical contracts and artifact support | Faker |
| MySql / PostgreSql / Sqlite | Release loading, plans, grammar adaptations, lexer realization and parser semantics | Grammar, Faker |
| Compiler | Parse Bison or Lemon source into the common model | Grammar |
| Tooling | Build and publish matching grammar and lexer artifacts | Grammar, individual dialects |
| Compatibility | Existing DB-specific generator and MySQL grammar/compiler facades | Generation, Grammar, Compiler, individual dialects, Faker |

The dialect layers cannot depend on one another, the common engine, Provider, or
tooling. Generation and Grammar cannot depend on a dialect or compiler. Bison
belongs to Compiler because both MySQL and PostgreSQL use that grammar format.
Lemon belongs there for the same reason: source syntax is a compilation concern.

`GenerationPlan::withStepBudget()` reserves enough expansions to finish the
remaining sentential form and prefers fewer expansions at the depth limit. SQLite
plans enable it. Other plans retain shortest-output selection, preserving existing
seeded behavior. This is a plan policy, with no dialect check inside the engine.
SQLite's implicit terminals are resolved during grammar adaptation, so the common
derivation can reject undeclared nonterminals consistently.

Public Provider methods and `StatementType` aliases retain their signatures.
The old DB-specific `SqlGenerator` classes only compose inputs and delegate to the
common engine. MySQL's old grammar facade uses the common symbol types; its old
symbol names are aliases. New code should use the common model and engine.
Compiler implementation namespaces moved to `SqlFaker\Compiler\Bison` and
`SqlFaker\Compiler\Lemon`; artifact orchestration moved to `SqlFaker\Tooling`.

## Architecture checks

Deptrac is configured using
[k-kinzal/php-ai-toolkit's setup-toolkit-deptrac skill](https://github.com/k-kinzal/php-ai-toolkit/blob/main/skills/setup-toolkit-deptrac/SKILL.md).
The toolkit supplies the setup workflow; the installed `vendor/bin/deptrac` is the
execution entry point. Deptrac 3 is the newest release line compatible with this
package's existing PHP 8.1 development platform pin. Deptrac 4 requires PHP 8.2.
The platform setting and supported PHP 8.1–8.5 test matrix are unchanged.

Run `composer deptrac` for dependency analysis and production-token assignment
checks, or `composer deptrac:debug` for assignment and unused-rule diagnostics.
`composer lint` runs Deptrac immediately after toolkit-based PHPStan, so the
existing lint CI job enforces the rules. There is no baseline or skipped violation.

Deptrac 3 reports imported PHPStan type aliases and PHP `class_alias` names as
uncovered dependencies, even though they do not declare classes. Consequently,
coverage of production classes is enforced with `debug:unassigned`, rather than
`--fail-on-uncovered`. Actual forbidden dependencies still fail `analyse`, and a
new production class outside the defined layers fails `debug:unassigned`.
