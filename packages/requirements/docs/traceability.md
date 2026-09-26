# Traceability

A passing test suite shows that the tested behavior works. It does not show that the tests follow the manual, the RFC or the ticket they were written from, or which parts of it nobody has tested. requirements links each statement of such a source to the specification that restates it and to the tests that verify it, and reports what is left over.

## Model

```
source ── scope ── unit ◀── evidence ── requirement ◀── specification ── tests
                        ◀── evidence ─────────────────── specification ── tests
```

| Term | Meaning |
|------|---------|
| Source | A document you trace: a manual, an RFC, a JSON catalog, an issue tracker. |
| Scope | The part of the source to cover, chosen by a selector, for example every paragraph in `main`. |
| Unit | One element, value or line in the scope. Coverage counts units. |
| Evidence | An exact quotation of one unit. It detects when the source changes. |
| Requirement | Optional. A quoted statement that several specifications implement. |
| Specification | A testable statement in EARS form, linked to its tests. It can be `unsupported`, with a reason, when the project deliberately does not implement the source. |
| Test | A PHPUnit method or a Behat scenario, or a test of a custom runner. |

Specifications that no source describes, such as decisions of your own, are declared with `source: null` and a rationale, so they are visible instead of silently mixed in.

## Workflow

1. Choose the source and the scope to cover, and write a [definition file](definitions.md) for it.
2. For each unit, add a specification that quotes it and links its tests, or an unsupported specification that explains why not.
3. Run `requirements lint` and `requirements check` to validate the files and the quotations.
4. Run `requirements coverage` to find the units nobody has accounted for.
5. Run `requirements spec` to run the linked tests.
6. In CI, gate the coverage of new and changed units with a [snapshot](cli.md#ci).

## What it does not prove

Matching quotations prove where a specification comes from, not that it means the same as its source, and passing tests prove only what they test. Reviewers still decide whether each specification and test is right. requirements makes that review possible for every unit, and never marks unknown behavior as unsupported by itself.
