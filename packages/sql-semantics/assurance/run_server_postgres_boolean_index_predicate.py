"""Check compound PostgreSQL partial index predicates and their native effects."""

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

LABEL = "postgres-boolean-index-predicate"
CONTRACT_NAME = "postgres-boolean-index-predicate-v1.json"
CONTRACT = "b9f964d7e2d8b0102584a0ee0322140340942beea0efe9ddf97ef168da0474e5"
COMPLETE_SEEDS = True
AXES = ("forms", "operators", "leftSlots", "rightSlots", "leftNegation", "rightNegation")
NAMES = ("label", "state", "a", "b", "c")
TYPES = ["int4", "int4", "int4", "int8", "bool"]
SLOTS = (2, 3, 4)
ORDERS = tuple(BITS)
LOGIC = ("AND", "OR", "NOT")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-boolean-index-predicate-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-boolean-index-predicate-exclusions")
    # The grouped negative requirements include separate OR-arity and parameter witnesses.
    spec["additionalPostgreSqlNegatives"] = ["OR without its right child", "Unresolved parameter in a nested branch"]
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def finite_ids(_, specification, _tag):
    profile = specification["finite"]
    identities = {
        (form, operator, left, right, bool(polarities & 1), bool(polarities & 2))
        for form in ("binary", "nested-left", "nested-right", "negated-binary")
        for operator in ("AND", "OR") for left in range(2, 5) for right in range(2, 5)
        for polarities in range(4)
    }
    require(len(identities) == 288 and identities == set(product(*(profile[axis] for axis in AXES))),
            "postgres-boolean-index-predicate-finite-size")
    return identities


def finite_id(_, actual, item):
    identity = binding_coordinates(item, actual)
    require(actual["createBtree"]["predicate"] == formula(*identity),
            "postgres-boolean-index-predicate-finite-tree", identity)
    return identity


def direct_finite_id(engine, actual, item, _request):
    return finite_id(engine, actual, item)


def analysis_finite_id(engine, actual, item, _authored):
    return finite_id(engine, actual, item)


def formula(form, operator, one, two, left_negated, right_negated):
    left, right = [one, left_negated], [two, right_negated]
    if form == "nested-left":
        return ["AND", [operator, left, [4, False]], right]
    if form == "nested-right":
        return ["OR", left, [operator, [4, True], right]]
    binary = [operator, left, right]
    if form == "negated-binary":
        return ["NOT", binary, None]
    require(form == "binary", "postgres-boolean-index-predicate-finite-shape", form)
    return binary


def fixture_rows(labels, a=11, b=7, c=True):
    return [
        [label, ordinal, None if ordinal & 1 else a, None if ordinal & 2 else b, None if ordinal & 4 else c]
        for ordinal, label in enumerate(labels)
    ]


def build(tag, predicate, *, unique=True, columns=NAMES, keys=((0, "ASC NULLS LAST"),), rows=None, **options):
    item = case(tag, keys, unique=unique, columns=columns, kinds=TYPES, predicate=predicate,
                rows=rows if rows is not None else fixture_rows(n % 3 for n in range(8)),
                probe_rows=options.pop("probe_rows", (0, 1, 3, 5)), **options)
    return {**item, "observeMembership": True}


def finite(tag, axes):
    return build(tag, formula(*axes), coordinate=list(axes))


def generate_tree(rng, depth):
    if depth == 0:
        return [rng.choice(SLOTS), bool(rng.getrandbits(1))]
    operator = rng.choice(LOGIC)
    if operator == "NOT":
        return [operator, generate_tree(rng, depth - 1), None]
    deep = generate_tree(rng, depth - 1)
    short = generate_tree(rng, rng.randrange(depth))
    left, right = (deep, short) if rng.getrandbits(1) else (short, deep)
    return [operator, left, right]


def generated(tag, seed, total):
    rng = Random(seed)
    for ordinal in range(total):
        predicate = generate_tree(rng, 1 + ordinal % 6)
        yield build(
            tag, predicate, unique=bool(rng.getrandbits(1)), nulls=bool(rng.getrandbits(1)),
            concurrently=bool(rng.getrandbits(1)), transaction=bool(rng.getrandbits(1)),
            scope=rng.choice(("persistent", "temporary")),
            keys=[(rng.choice((0, *SLOTS)), rng.choice(ORDERS)) for _ in range(1 + ordinal % 3)],
            included=[rng.randrange(5) for _ in range(ordinal % 4)],
            columns=rng.choice((NAMES, ("label", "state", 'A"x', "b", "有効"))),
            rows=fixture_rows((rng.choice((None, 1, 2, 7)) for _ in range(8)), a=-1 - ordinal, b=4_000_000_000 + ordinal,
                              c=bool(rng.getrandbits(1))),
        )


def supplemental(tag):
    one, two = [2, False], [4, True]
    for predicate in (["NOT", one, None], ["NOT", ["NOT", one, None], None], ["AND", one, ["OR", two, one]]):
        item = build(tag, predicate, unique=False)
        yield {**item, "predicateSpelling": {"2": "schema", "4": "table"}}


def negatives(tag):
    one, two = [2, False], [4, True]
    arity = refusal("NOT needs one operand and AND or OR needs two")
    yield build(tag, ["NOT", one, two]), arity
    yield build(tag, ["AND", one, None]), arity
    yield build(tag, ["OR", one, None]), arity
    yield build(tag, ["AND", None, two]), {"exception": "TypeError", "message": "Argument #2 ($left)"}
    yield build(tag, ["OR", one, ["NOT", [5, True], None]]), refusal("predicate must refer to a column")
    request = analyzed(build(tag, ["AND", one, two]))
    fragment = '("c" IS NOT NULL)'
    for expression, boundary in (
        ("(unrelated.c IS NULL)", refusal("unrelated", "SemanticException")),
        ("(ROW(a, b) IS NULL)", refusal("direct scalar column NULL test")),
        ("($1 IS NULL)", refusal("direct scalar column NULL test")),
    ):
        yield {**request, "sql": request["sql"].replace(fragment, expression)}, boundary


def requests(_, tag, spec):
    for ordinal, axes in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = finite(tag, axes)
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", item, item, None
    for ordinal, item in enumerate(supplemental(tag)):
        yield f"edge/{ordinal}", analyzed(item), item, None
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", item, item, None
    for ordinal, (request, boundary) in enumerate(negatives(tag)):
        yield f"negative/{ordinal}", request, None, boundary


def measure(forms, predicate, depth=1):
    if len(predicate) == 2:
        forms[f"slot/{predicate[0]}"] += 1
        forms[f"polarity/{predicate[1]}"] += 1
        forms[f"depth/{depth - 1}"] += 1
        return
    operator, left, right = predicate
    forms[f"operator/{operator}"] += 1
    if len(left) == 3:
        forms[f"left/{operator}"] += 1
    measure(forms, left, depth + 1)
    if right is not None:
        if len(right) == 3:
            forms[f"right/{operator}"] += 1
        measure(forms, right, depth + 1)


def sample_forms(forms, reply, _, item):
    root = reply["createBtree"]
    measure(forms, root["predicate"])
    forms[f"scope/{root['table'][0]}"] += 1
    forms[f"runtime/{expected(item)['sqlState']}"] += 1
    for field in ("unique", "nullsNotDistinct", "concurrently"):
        forms[f"{field}/{root[field]}"] += 1
    forms[f"transaction/{item['transaction']}"] += 1


def required_forms(*_):
    return {
        *(f"slot/{slot}" for slot in SLOTS), *(f"depth/{depth}" for depth in range(1, 7)),
        *(f"operator/{operator}" for operator in LOGIC), *(f"left/{operator}" for operator in LOGIC),
        *(f"right/{operator}" for operator in ("AND", "OR")),
        *(f"{axis}/{value}" for axis in ("polarity", "unique", "nullsNotDistinct", "concurrently", "transaction")
          for value in (False, True)),
        "scope/fixture", "scope/pg_temp", "runtime/00000", "runtime/23505", "runtime/25001",
    }


def controls():
    item = build("pg-17.2", ["AND", [2, True], [3, False]], unique=False)
    observed = expected(item)
    check_observation(item, observed, "valid composed membership")
    changed = json.loads(json.dumps(observed))
    changed["membership"][0] = not changed["membership"][0]
    try:
        check_observation(item, changed, "changed membership for the same typed rows")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"right-child-membership-required": {"input": item, "witness": error.detail}}
    raise Failure("postgres-boolean-index-predicate-comparator-accepted")


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/Index/Btree/"
    condition, node = path + "IndexCondition.php", path + "IndexBooleanPredicate.php"
    refusal_cases = list(negatives(tag))
    arity, arity_boundary = refusal_cases[0]
    scope, scope_boundary = refusal_cases[4]
    composed = build(tag, ["AND", [2, True], [3, False]], unique=False)
    negated = build(tag, ["NOT", ["OR", [2, True], [3, False]], None], unique=False)
    return [
        RenderFault("postgres-index-boolean-admit-wrong-arity", node,
                    "($operator === LogicalOperator::Not) === ($right === null)", "true",
                    {"boundary": arity_boundary}, INVALID, {**arity, "constructionOnly": True}),
        RenderFault("postgres-index-boolean-omit-right-scope", condition,
                    "if ($node->right !== null) {", "if (false) {",
                    {"boundary": scope_boundary}, INVALID, {**scope, "constructionOnly": True}),
        RenderFault("postgres-index-boolean-exchange-and-or", condition,
                    "' ' . $node->operator->value . ' '", "' OR '", composed, "meaning-mismatch"),
        RenderFault("postgres-index-boolean-erase-negation", condition,
                    "'(' . $node->operator->value . ' '", "'('", negated, "meaning-mismatch"),
        RenderFault("postgres-index-boolean-erase-binary-right", condition,
                    "$pending[] = $node->right;" + "\n                $pending[] = ' ' . $node->operator->value . ' ';",
                    "", composed, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
