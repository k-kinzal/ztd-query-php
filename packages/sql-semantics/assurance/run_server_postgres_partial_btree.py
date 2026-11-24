"""Verify PostgreSQL B-tree membership from a resolved scalar NULL predicate."""

import json
from itertools import product
from random import Random
import sys

from postgres_create_btree_cases import BITS, analyzed, binding_coordinates, case, expected, refusal
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main
from run_server_postgres_create_btree import (
    INVALID, OBSERVER, check_observation, check_preparation, check_value, fault_probe, observation,
    observer_request, prepare, replay_fault,
)
from sqlite_domain import RenderFault

LABEL = "postgres-partial-btree"
CONTRACT_NAME = "postgres-partial-btree-v1.json"
CONTRACT = "b0af12a3006af20f6a3f304251e134b44816c8952c3f0cada6b54ed9eeb9c876"
COMPLETE_SEEDS = True
HEADINGS = ["a", "b", "ready"]
ROWS = [[None, 7, False], [21, 13, None], [33, 19, True], [None, None, True]]
AXES = ("keys", "selector", "polarity", "uniqueNullPolicies", "authoredQualifiers")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-partial-btree-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-partial-btree-exclusions")

    return spec, {"postgres": drop_contract()[1]["postgres"]}


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def coordinates(keys, selector, polarity, flags, spelling):
    return "partial", tuple(keys), selector, polarity, tuple(flags), spelling


def finite_ids(_, spec, _tag):
    result = set(coordinates(*axes) for axes in grid(spec))
    require(len(result) == 288, "postgres-partial-btree-finite-size")

    return result


def finite_id(_, actual, item):
    return binding_coordinates(item, actual)


def direct_finite_id(engine, actual, item, _request):
    return finite_id(engine, actual, item)


def analysis_finite_id(engine, actual, item, _authored):
    return finite_id(engine, actual, item)


def build(tag, keys, selector, negated, *, unique=False, nulls=False, **options):
    return case(tag, keys, unique=unique, nulls=nulls,
                columns=options.pop("columns", HEADINGS), rows=options.pop("rows", ROWS),
                predicate=[selector, negated], probe_rows=(0, 1, 2), **options)


def finite(tag, keys, selector, polarity, flags, spelling):
    unique, nulls = flags
    return build(tag, [(key, "ASC NULLS LAST") for key in keys], selector, polarity == "not-null",
                 unique=unique, nulls=nulls, coordinate=list(coordinates(keys, selector, polarity, flags, spelling)))


def generated(tag, seed, total):
    rng = Random(seed)
    for ordinal in range(total):
        keys = [(rng.randrange(3), rng.choice(tuple(BITS))) for _ in range(1 + ordinal % 5)]
        selector = rng.randrange(3)
        negated = bool(rng.getrandbits(1))
        rows = [
            [None if rng.getrandbits(1) else 11 + r,
             r + 613 * ordinal if r < 3 else None,
             rng.choice((True, False, None))]
            for r in range(4)
        ]
        header = rng.choice((HEADINGS, ["a", "b", 'Ready"state'], ["key", "wide", "有効"]))
        yield build(tag, keys, selector, negated, scope=rng.choice(("persistent", "temporary")),
                    unique=bool(rng.getrandbits(1)), nulls=bool(rng.getrandbits(1)),
                    concurrently=bool(rng.getrandbits(1)), transaction=bool(rng.getrandbits(1)),
                    included=[rng.randrange(3) for _ in range(rng.randrange(4))],
                    columns=header, rows=rows)


def negatives(tag):
    keys = [(0, "ASC NULLS LAST")]
    yield build(tag, keys, -1, True), refusal("nonnegative column slot")
    yield build(tag, keys, 3, True), refusal("predicate must refer to a column")
    request = analyzed(build(tag, keys, 0, True))
    boundary = request["sql"].index(" WHERE ")
    prefix = request["sql"][:boundary] + " WHERE "
    yield {**request, "sql": prefix + "missing IS NOT NULL"}, refusal("Column missing does not exist.", "SemanticException")
    yield {**request, "sql": prefix + "unrelated.a IS NULL"}, refusal("unrelated", "SemanticException")
    for expression in ("ROW(a, b) IS NULL", "$1 IS NULL"):
        yield {**request, "sql": prefix + expression}, refusal("direct scalar column NULL test")


def requests(_, tag, specification):
    for number, axes in enumerate(grid(specification)):
        item = finite(tag, *axes)
        yield f"finite/{number}", analyzed(item), item, None
        yield f"direct/{number}", item, item, None
    for seed in specification["exploration"]["seeds"]:
        for number, item in enumerate(generated(tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{number}", item, item, None
    for number, (request, failure) in enumerate(negatives(tag)):
        yield f"negative/{number}", request, None, failure


def sample_forms(forms, reply, _, item):
    root = reply["createBtree"]
    slot, negation = root["predicate"]
    forms[f"predicate/{slot}"] += 1
    forms[f"polarity/{negation}"] += 1
    forms[f"scope/{root['table'][0]}"] += 1
    forms[f"range/{len(root['keys'])}"] += 1
    forms[f"payload/{bool(root['included'])}"] += 1
    forms[f"runtime/{expected(item)['sqlState']}"] += 1
    for field in ("unique", "nullsNotDistinct", "concurrently"):
        forms[f"{field}/{root[field]}"] += 1
    forms[f"transaction/{item['transaction']}"] += 1


def required_forms(*_):
    return {
        *(f"predicate/{slot}" for slot in range(3)), *(f"range/{arity}" for arity in range(1, 6)),
        *(f"{axis}/{value}" for axis in ("polarity", "payload", "unique", "nullsNotDistinct", "concurrently", "transaction")
          for value in (False, True)),
        "scope/fixture", "scope/pg_temp", "runtime/00000", "runtime/23505", "runtime/25001",
    }


def controls():
    item = build("pg-17.2", [(0, "ASC NULLS LAST")], 0, True, unique=True, nulls=True)
    observation = expected(item)
    check_observation(item, observation, "valid partial membership")
    erased = json.loads(json.dumps(observation))
    erased["after"]["index"]["predicate"] = None
    try:
        check_observation(item, erased, "predicate lost")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"partial-membership-required": {"input": item, "witness": error.detail}}
    raise Failure("postgres-partial-btree-comparator-accepted")


def faults(_, tag):
    source = "sql-semantics-postgres/src/Ir/Schema/Index/Btree/CreateIndex.php"
    partial = build(tag, [(0, "ASC NULLS LAST")], 0, True, unique=True, nulls=True)
    mismatch = build(tag, [(1, "ASC NULLS LAST")], 1, False, unique=True)
    invalid, boundary = list(negatives(tag))[1]
    return [
        RenderFault("postgres-partial-btree-drop-null-filter", source, "$this->predicate === null ? '' : ' WHERE ('",
                    "true ? '' : ' WHERE ('", partial, "meaning-mismatch"),
        RenderFault("postgres-partial-btree-other-null-column", source, "$this->table->columns[$this->predicate->slot]",
                    "$this->table->columns[0]", mismatch, "meaning-mismatch"),
        RenderFault("postgres-partial-btree-invert-null-condition", source, "$this->predicate->negated ? ' IS NOT NULL)'",
                    "!$this->predicate->negated ? ' IS NOT NULL)'", partial, "meaning-mismatch"),
        RenderFault("postgres-partial-btree-admit-absent-null-column", source,
                    "$predicate === null || isset($table->columns[$predicate->slot])", "true",
                    {"boundary": boundary}, INVALID, invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
