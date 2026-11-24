"""Compare exact bound Boolean index tests against native catalog and write observations."""

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
    INVALID, OBSERVER, check_observation, check_preparation, check_value, fault_probe,
    observation, observer_request, prepare, replay_fault,
)
from sqlite_domain import RenderFault

LABEL = "postgres-index-truth-tests"
CONTRACT_NAME = "postgres-index-truth-tests-v1.json"
CONTRACT = "7c0995045b9c80f57461da353da10591ccf776d996e6d0bfded88a519fe662b2"
COMPLETE_SEEDS = True
AXES = ("forms", "operators", "slots", "qualifiers")
FORMS = ("plain", "negated", "and-null", "or-truth")
TRUTH_TESTS = ("IS TRUE", "IS NOT TRUE", "IS FALSE", "IS NOT FALSE", "IS UNKNOWN", "IS NOT UNKNOWN")
QUALIFIERS = {"local": "unqualified", "relation": "table", "schema": "schema"}
NAMES = ["label", "state", "p", "q"]
KINDS = ["int4", "int4", "bool", "bool"]
STATES = tuple(product((True, False, None), repeat=2))
VALUES = [[index % 4, index, p, q] for index, (p, q) in enumerate(STATES)]


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-index-truth-tests-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-index-truth-tests-exclusions")
    spec["additionalPostgreSqlNegatives"] = [
        "Missing owner slot", "Integer field in a truth-test slot",
        "Missing parsed column", "Nested truth test of a non-Boolean column", "Non-column truth operand",
    ]
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def finite_ids(_, spec, _tag):
    ids = {(form, test, slot, qualifier) for form in FORMS for test in TRUTH_TESTS
           for slot in range(2, 4) for qualifier in QUALIFIERS}
    require(len(ids) == 144 and ids == set(product(*(spec["finite"][axis] for axis in AXES))),
            "postgres-index-truth-tests-finite-size")
    return ids


def finite_id(_, actual, item):
    coordinate = binding_coordinates(item, actual)
    require(actual["createBtree"]["predicate"] == expression(*coordinate[:3]),
            "postgres-index-truth-tests-finite-tree", coordinate)
    return coordinate


def direct_finite_id(engine, actual, item, _request):
    return finite_id(engine, actual, item)


def analysis_finite_id(engine, actual, item, _authored):
    return finite_id(engine, actual, item)


def expression(form, operator, slot):
    leaf, other = [slot, operator], 5 - slot
    if form == "plain":
        return leaf
    if form == "negated":
        return ["NOT", leaf, None]
    if form == "and-null":
        return ["AND", leaf, [other, False]]
    require(form == "or-truth", "postgres-index-truth-tests-form", form)
    return ["OR", [other, "IS NOT FALSE"], leaf]


def build(tag, predicate, *, rows=VALUES, unique=True, keys=((0, "ASC NULLS LAST"),), **options):
    item = case(tag, keys, rows=rows, unique=unique, kinds=KINDS,
                columns=options.pop("columns", NAMES), predicate=predicate, probe_rows=(), **options)
    return {**item, "observeMembership": True, "probeValues": [[0, 9, None, False]], "probeFailedBuild": True}


def finite(tag, coordinate):
    form, test, slot, qualifier = coordinate
    item = build(tag, expression(form, test, slot), coordinate=list(coordinate))
    return {**item, "predicateSpelling": {str(slot): QUALIFIERS[qualifier]}}


def tree(rng, depth):
    if depth == 0:
        slot = rng.choice((2, 3))
        return [slot, rng.choice(TRUTH_TESTS)] if rng.getrandbits(1) else [rng.randrange(4), bool(rng.getrandbits(1))]
    operator = rng.choice(("AND", "OR", "NOT"))
    child = tree(rng, depth - 1)
    if operator == "NOT":
        return [operator, child, None]
    sibling = tree(rng, rng.randrange(depth))
    return [operator, child, sibling] if rng.getrandbits(1) else [operator, sibling, child]


def generated(tag, seed, total):
    rng = Random(seed)
    for ordinal in range(total):
        mixed = tree(rng, 1 + ordinal % 4)
        if ordinal % 2:
            mixed = ["AND", [rng.choice((2, 3)), rng.choice(TRUTH_TESTS)], mixed]
        yield build(
            tag, mixed, unique=bool(rng.getrandbits(1)),
            nulls=bool(rng.getrandbits(1)), scope=rng.choice(("persistent", "temporary")),
            concurrently=bool(rng.getrandbits(1)), transaction=bool(rng.getrandbits(1)),
            keys=[(rng.randrange(4), rng.choice(tuple(BITS))) for _ in range(1 + ordinal % 3)],
            included=[rng.randrange(4) for _ in range(rng.randrange(4))],
            columns=rng.choice((NAMES, ["label", "state", 'P"ready', "真"])),
            rows=[[rng.choice((None, 0, 1, 5)), i, p, q] for i, (p, q) in enumerate(STATES)],
        )


def negatives(tag):
    yield build(tag, [-1, "IS TRUE"]), refusal("nonnegative column slot")
    yield build(tag, [4, "IS TRUE"]), refusal("Boolean column of the indexed table")
    yield build(tag, ["OR", [2, "IS NOT FALSE"], [0, "IS UNKNOWN"]]), refusal("Boolean column of the indexed table")
    authored = analyzed(build(tag, [2, "IS TRUE"]))
    head = authored["sql"].split(" WHERE ", 1)[0] + " WHERE "
    for condition, boundary in (
        ("unknown.p IS FALSE", refusal("unknown", "SemanticException")),
        ("missing IS UNKNOWN", refusal("Column missing does not exist.", "SemanticException")),
        ("label IS TRUE", refusal("Argument of IS TRUE must be type boolean", "SemanticException")),
        ("p IS FALSE AND label IS UNKNOWN", refusal("Argument of IS UNKNOWN must be type boolean", "SemanticException")),
        ("$1 IS TRUE", refusal("Boolean truth test")),
        ("(TRUE OR FALSE) IS TRUE", refusal("Boolean truth test")),
    ):
        yield {**authored, "sql": head + condition}, boundary


def requests(_, tag, spec):
    for ordinal, coordinate in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = finite(tag, coordinate)
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", item, item, None
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", item, item, None
    for ordinal, (request, boundary) in enumerate(negatives(tag)):
        yield f"negative/{ordinal}", request, None, boundary


def leaf_forms(predicate, depth=0):
    if len(predicate) == 2:
        slot, operation = predicate
        if isinstance(operation, str):
            yield f"test/{operation}"
            yield f"slot/{slot}"
        else:
            yield "null-leaf"
        yield f"leaf-depth/{depth}"
        return
    operation, left, right = predicate
    yield f"node/{operation}"
    yield from leaf_forms(left, depth + 1)
    if right is not None:
        yield from leaf_forms(right, depth + 1)


def sample_forms(forms, reply, _, item):
    root = reply["createBtree"]
    forms.update(leaf_forms(root["predicate"]))
    forms[f"scope/{root['table'][0]}"] += 1
    forms[f"runtime/{expected(item)['sqlState']}"] += 1
    for label in ("unique", "nullsNotDistinct", "concurrently"):
        forms[f"{label}/{root[label]}"] += 1
    forms[f"transaction/{item['transaction']}"] += 1


def required_forms(*_):
    return {*("test/" + test for test in TRUTH_TESTS), *(f"slot/{index}" for index in (2, 3)),
            *(f"node/{kind}" for kind in ("AND", "OR", "NOT")), *(f"leaf-depth/{depth}" for depth in range(6)),
            *(f"{axis}/{value}" for axis in ("unique", "nullsNotDistinct", "concurrently", "transaction") for value in (False, True)),
            "null-leaf", "scope/fixture", "scope/pg_temp", "runtime/00000", "runtime/23505", "runtime/25001"}


def controls():
    item = build("pg-17.2", [2, "IS NOT FALSE"], unique=False)
    sample = expected(item)
    check_observation(item, sample, "Boolean test with false and unknown both present")
    altered = json.loads(json.dumps(sample))
    altered["membership"][6] = False
    try:
        check_observation(item, altered, "wrong truth value on unknown")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"unknown-distinguished-from-false": {"input": item, "witness": error.detail}}
    raise Failure("postgres-index-truth-tests-comparator-accepted")


def faults(_, tag):
    source = "sql-semantics-postgres/src/Ir/Schema/Index/Btree/IndexCondition.php"
    invalid, boundary = list(negatives(tag))[2]
    truth = build(tag, [2, "IS TRUE"], unique=False)
    unknown = build(tag, [2, "IS UNKNOWN"], unique=False)
    spelling = "$node->operator->value . ')'"
    prefix = "$table->columns[$node->slot]->quoted() . ' ' . "
    return [
        RenderFault("postgres-index-truth-admit-int", source,
                    "($table->columnKinds[$node->slot] ?? null) === ColumnKind::Boolean", "true",
                    {"boundary": boundary}, INVALID, {**invalid, "constructionOnly": True}),
        RenderFault("postgres-index-truth-bind-other-column", source, prefix + spelling,
                    "$table->columns[3]->quoted() . ' ' . " + spelling, truth, "meaning-mismatch"),
        RenderFault("postgres-index-truth-accept-unknown-as-true", source, prefix + spelling,
                    prefix + "'IS NOT FALSE' . ')'", truth, "meaning-mismatch"),
        RenderFault("postgres-index-truth-negate-unknown", source, prefix + spelling,
                    prefix + "'IS NOT UNKNOWN' . ')'", unknown, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
