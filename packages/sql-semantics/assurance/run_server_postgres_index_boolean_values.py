"""Check resolved nullable PostgreSQL index fields and native conflict inference."""

import json
from itertools import product
from random import Random
import sys

from postgres_create_btree_cases import BITS, analyzed, binding_coordinates, case, expected, refusal
from postgres_index_inference import EXPRESSIONS
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main
from run_server_postgres_create_btree import (
    INVALID, OBSERVER, check_observation, check_preparation, check_value, fault_probe,
    observation, observer_request, prepare, replay_fault,
)
from run_server_postgres_index_truth_tests import KINDS, NAMES, QUALIFIERS, STATES, TRUTH_TESTS
from sqlite_domain import RenderFault

LABEL = "postgres-index-boolean-values"
CONTRACT_NAME = "postgres-index-boolean-values-v1.json"
CONTRACT = "51a082173a7d404983a9ea5fb251ffceeb3b4e64f56c1a3960aa88ef2e93a374"
COMPLETE_SEEDS = True
UNARY = ("bare", "not")
BINARY = ("and", "or", "not-and", "not-or", "and-null-leaf", "or-truth-leaf")
SLOTS = (2, 3)
ROWS = [[i % 4, i, p, q] for i, (p, q) in enumerate(STATES)]
ARBITER_ROWS = [[1, 0, True, True], [2, 1, False, False], [3, 2, None, None]]


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-index-boolean-values-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-index-boolean-values-exclusions")
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def grids(spec):
    points = spec["finite"]
    yield from product(("truth",), points["unaryForms"], points["slots"], (None,), points["qualifiers"])
    yield from product(("truth",), points["binaryForms"], points["slots"], points["slots"], points["qualifiers"])
    yield from product(("inference",), points["inferenceForms"], points["inferenceProbes"])


def finite_ids(_, spec, _tag):
    definitions = {"p", "NOT p", "p IS TRUE", "p IS FALSE", "p OR q", "p AND q",
                   "NOT (p OR q)", "NOT (p AND q)", "p AND q IS NOT FALSE"}
    probes = {"p", "NOT p", "p IS TRUE", "p IS FALSE", "p OR q", "p AND q", "q AND p", "p AND q IS NOT FALSE"}
    symbolic = {
        ("truth", form, slot, other, spelling)
        for form in (*UNARY, *BINARY) for slot in SLOTS
        for other in ((None,) if form in UNARY else SLOTS) for spelling in QUALIFIERS
    } | {("inference", definition, probe) for definition in definitions for probe in probes}
    require(len(symbolic) == 156 and symbolic == set(grids(spec)), "postgres-index-boolean-values-finite-size")
    return symbolic


def finite_id(_, actual, item):
    coordinate = binding_coordinates(item, actual)
    expression = truth_shape(*coordinate[1:4]) if coordinate[0] == "truth" else EXPRESSIONS[coordinate[1]]
    require(actual["createBtree"]["predicate"] == expression, "postgres-index-boolean-values-coordinate", coordinate)
    return coordinate


def direct_finite_id(engine, actual, item, _request):
    return finite_id(engine, actual, item)


def analysis_finite_id(engine, actual, item, _authored):
    return finite_id(engine, actual, item)


def truth_shape(form, slot, other):
    field = [slot, "column"]
    if form == "bare":
        return field
    if form == "not":
        return ["NOT", field, None]
    if form == "and-null-leaf":
        return ["AND", field, [other, False]]
    if form == "or-truth-leaf":
        return ["OR", field, [other, "IS NOT TRUE"]]
    require(form in ("and", "or", "not-and", "not-or"), "postgres-index-boolean-values-form", form)
    _, _, name = form.rpartition("-")
    binary = [name.upper(), field, [other, "column"]]
    return ["NOT", binary, None] if form.startswith("not-") else binary


def build(tag, predicate, *, rows=ROWS, keys=((0, "ASC NULLS LAST"),), unique=True, **options):
    label = case(tag, keys, columns=options.pop("columns", NAMES), kinds=KINDS, rows=rows,
                 unique=unique, predicate=predicate, probe_rows=(), **options)
    return {**label, "observeMembership": True, "observeTruthValues": True,
            "probeFailedBuild": True, "probeValues": [[0, 9, None, False]]}


def inference(tag, expression, probe, rows=ARBITER_ROWS):
    value = build(tag, EXPRESSIONS[expression], rows=rows, coordinate=["inference", expression, probe])
    return {**value, "conflictProbe": [expression, probe, [1, 9, True, True]], "probeValues": []}


def finite(tag, coordinate):
    if coordinate[0] == "inference":
        return inference(tag, *coordinate[1:])
    _, shape, slot, other, spelling = coordinate
    item = build(tag, truth_shape(shape, slot, other), coordinate=list(coordinate))
    return {**item, "predicateSpelling": {str(slot): QUALIFIERS[spelling]}}


def tree(rng, depth):
    if depth == 0:
        return [rng.choice(SLOTS), rng.choice(("column", False, True, *TRUTH_TESTS))]
    operator = rng.choice(("AND", "OR", "NOT"))
    deep = tree(rng, depth - 1)
    if operator == "NOT":
        return ["NOT", deep, None]
    shallow = tree(rng, rng.randrange(depth))
    return [operator, deep, shallow] if rng.getrandbits(1) else [operator, shallow, deep]


def generated(tag, seed, quantity):
    rng = Random(seed)
    arbiter_edges = (("p", "p"), ("NOT p", "NOT p"), ("p IS TRUE", "p"))
    for ordinal in range(quantity):
        if ordinal < len(arbiter_edges):
            yield inference(tag, *arbiter_edges[ordinal])
            continue
        field = [rng.choice(SLOTS), "column"]
        operation = rng.choice(("AND", "OR"))
        yield build(
            tag, [operation, field, tree(rng, ordinal % 6)],
            unique=bool(rng.getrandbits(1)), nulls=bool(rng.getrandbits(1)),
            concurrently=bool(rng.getrandbits(1)), transaction=bool(rng.getrandbits(1)),
            scope=rng.choice(("persistent", "temporary")),
            keys=[(rng.randrange(4), rng.choice(tuple(BITS))) for _ in range(1 + ordinal % 3)],
            included=[rng.randrange(4) for _ in range(ordinal % 4)],
            columns=rng.choice((NAMES, ["label", "state", 'P"read', "可"])),
            rows=[[rng.choice((None, 0, 1, 10)), i, p, q] for i, (p, q) in enumerate(STATES)],
        )


def negatives(tag):
    boundary = refusal("Boolean column of the indexed table")
    yield build(tag, [-1, "column"]), refusal("nonnegative column slot")
    yield build(tag, [4, "column"]), boundary
    yield build(tag, [0, "column"]), boundary
    yield build(tag, ["OR", [2, "column"], ["NOT", [0, "column"], None]]), boundary
    authored = analyzed(build(tag, [2, "column"]))
    prefix = authored["sql"].split(" WHERE ", 1)[0] + " WHERE "
    for condition, error in (
        ("unrelated.p", refusal("unrelated", "SemanticException")),
        ("missing", refusal("Column missing does not exist.", "SemanticException")),
        ("label", refusal("of WHERE must be type boolean", "SemanticException")),
        ("NOT label", refusal("of NOT must be type boolean", "SemanticException")),
    ):
        yield {**authored, "sql": prefix + condition}, error


def requests(_, tag, spec):
    for ordinal, coordinate in enumerate(grids(spec)):
        item = finite(tag, coordinate)
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", item, item, None
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", item, item, None
    for ordinal, (request, reason) in enumerate(negatives(tag)):
        yield f"negative/{ordinal}", request, None, reason


def measure(predicate, forms, depth=0):
    if len(predicate) == 2:
        slot, kind = predicate
        family = "column" if kind == "column" else ("truth-test" if isinstance(kind, str) else "null-test")
        forms[f"leaf/{family}"] += 1
        forms[f"slot/{slot}"] += 1
        forms[f"depth/{depth}"] += 1
        return
    name, one, two = predicate
    forms[f"operator/{name}"] += 1
    measure(one, forms, depth + 1)
    if two is not None:
        measure(two, forms, depth + 1)


def sample_forms(forms, reply, _, item):
    root = reply["createBtree"]
    measure(root["predicate"], forms)
    forms[f"scope/{root['table'][0]}"] += 1
    comparable = expected(item)
    forms[f"runtime/{comparable['sqlState']}"] += 1
    if "inference" in comparable:
        result = comparable["inference"]
        forms[f"inference/{result['sqlState']}/{result['insertCount']}"] += 1


def required_forms(*_):
    return {
        "leaf/column", "leaf/truth-test", "leaf/null-test", "slot/2", "slot/3",
        "operator/AND", "operator/OR", "operator/NOT", *(f"depth/{value}" for value in range(7)),
        "scope/fixture", "scope/pg_temp", "runtime/00000", "runtime/23505", "runtime/25001",
        "inference/00000/0", "inference/00000/1", "inference/42P10/None",
    }


def controls():
    item = inference("pg-17.2", "p", "p")
    good = expected(item)
    check_observation(item, good, "native bare-column inference")
    witnesses = {}
    for label, replacement in (
        ("unknown-remains-unknown", {"truthValues": [True, False, False]}),
        ("arbiter-inference-required", {"inference": {"sqlState": "42P10", "insertCount": None}}),
    ):
        try:
            check_observation(item, {**good, **replacement}, label)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            witnesses[label] = {"input": item, "witness": error.detail}
        else:
            raise Failure("postgres-index-boolean-values-comparator-accepted", label)
    return witnesses


def faults(_, tag):
    source = "sql-semantics-postgres/src/Ir/Schema/Index/Btree/IndexCondition.php"
    incorrect, reason = list(negatives(tag))[3]
    reading = build(tag, [2, "column"], unique=False)
    negation = build(tag, ["NOT", [2, "column"], None], unique=False)
    bare = "$table->columns[$node->slot]->quoted() . ')'"
    nullable = inference(tag, "p", "p", rows=[[1, 0, True, True], [2, 1, False, False], [3, 2, False, None]])
    return [
        RenderFault("postgres-index-boolean-value-admit-int", source,
                    "($table->columnKinds[$node->slot] ?? null) === ColumnKind::Boolean", "true",
                    {"boundary": reason}, INVALID, {**incorrect, "constructionOnly": True}),
        RenderFault("postgres-index-boolean-value-bind-other-field", source, bare,
                    "$table->columns[3]->quoted() . ')'", reading, "meaning-mismatch"),
        RenderFault("postgres-index-boolean-value-force-truth", source, bare,
                    "$table->columns[$node->slot]->quoted() . ' IS TRUE)'", nullable, "meaning-mismatch"),
        RenderFault("postgres-index-boolean-value-collapse-unknown-under-not", source, bare,
                    "$table->columns[$node->slot]->quoted() . ' AND ' . $table->columns[$node->slot]->quoted() . ' IS NOT NULL)'",
                    negation, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
