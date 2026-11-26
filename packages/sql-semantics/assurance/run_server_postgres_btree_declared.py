"""Verify that a PostgreSQL B-tree binds the declared collation of the selected text field."""

import json
import sys

import postgres_btree_declared_cases as laws
import postgres_btree_text_cases as text
from postgres_create_btree_cases import refusal
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_join import main
from run_server_postgres_create_btree import check_preparation, observation, prepare, specifications as profiles
from run_server_postgres_btree_text import check_value
from sqlite_domain import RenderFault

LABEL = "postgres-btree-declared"
OBSERVER = "postgres_btree_text_observer.py"
CONTRACT_NAME = "postgres-btree-declared-collation-evidence-v1.json"
CONTRACT = "0f82eea470f54278f3dbf35dc9a7e4a77a598e094b19efc390a9f43674003508"
TYPE = refusal("A B-tree text key requires its resolved collation, and other keys cannot declare one.")
STOCK = refusal("An explicitly collated PostgreSQL B-tree key requires stock pg_catalog.C and its default text operator class.")
LOST = "postgres-btree-declared-collation-lost"
ASSUMED = "postgres-btree-declared-collation-assumed"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-btree-declared-contract")
    scope = json.loads(path.read_text())
    authority = scope["reference"]
    require(digest(TOOL / authority["file"]) == authority["sha256"] and scope["exploration"]["allowedExclusions"] == [],
            "postgres-btree-declared-reference")
    return scope, profiles()[1]


def corpus(specification):
    finite = specification["finite"]
    exact = laws.identities()
    require(set(laws.coordinates(finite)) == exact and len(exact) == finite["coordinates"]
            and finite["routes"] == [laws.DIRECT, *laws.ANALYSIS], "postgres-btree-declared-finite")
    return exact


def finite_ids(_, spec, _tag):
    return {(*axes, route) for axes in corpus(spec) for route in laws.ANALYSIS}


def direct_finite_ids(_, spec, _tag):
    return {(*axes, laws.DIRECT) for axes in corpus(spec)}


def finite_id(_, response, item):
    check_value(item, response)
    return tuple(item["coordinate"])


def analysis_finite_id(engine, response, item, command):
    same(laws.statement(item), command)
    return finite_id(engine, response, item)


def direct_finite_id(engine, response, item, request):
    same(text.direct(item), request)
    return finite_id(engine, response, item)


def refusals(tag):
    for name in ("default", "posix", "unqualified", "neighbor", "decoy"):
        yield laws.analyzed(laws.off_profile(tag, name)), TYPE
    item = laws.case(tag, "persistent", "one-text-key", laws.ORDERS[0], laws.FORMS[1], laws.ANALYSIS[0])
    request = laws.analyzed(item)
    yield {**request, "sql": request["sql"].replace("pg_catalog.text_ops", "pg_catalog.text_pattern_ops")}, STOCK


def requests(_, tag, spec):
    for ordinal, coordinate in enumerate(laws.coordinates(spec["finite"])):
        for variant, route in enumerate(laws.ANALYSIS):
            item = laws.case(tag, *coordinate, route)
            yield f"finite/{ordinal * len(laws.ANALYSIS) + variant}", laws.analyzed(item), item, None
        item = laws.case(tag, *coordinate, laws.DIRECT)
        yield f"direct/{ordinal}", text.direct(item), item, None
    exploration = spec["exploration"]
    for seed in exploration["seeds"]:
        for ordinal, item in enumerate(laws.generated(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", laws.analyzed(item), item, None
        for ordinal, item in enumerate(laws.generated(tag, seed + 1, exploration["directCasesPerSeed"], direct=True)):
            yield f"direct-seed/{seed}/{ordinal}", text.direct(item), item, None
    for ordinal, (request, boundary) in enumerate(refusals(tag)):
        yield f"negative/{ordinal}", request, None, boundary


def observer_request(item, command):
    return {"sql": command, "trial": item}


def check_observation(item, actual, command):
    predicted = laws.expected(item)
    witness = {"sql": command, "expected": predicted, "observed": actual}
    require(actual["status"] == "postgres-text-btree", "postgres-btree-declared-observer", witness)
    require(actual == predicted, "meaning-mismatch", witness)


def sample_forms(forms, response, _, item):
    value = response["createBtree"]
    placement, _, _, form, route = item["coordinate"]
    forms[f"placement/{placement}"] += 1
    forms[f"owner/{value['table'][0]}"] += 1
    forms[f"route/{route}"] += 1
    forms[f"class/{form}"] += 1
    forms[f"kind-mix/{any(len(term) < 3 for term in value['keys'])}"] += 1
    forms[f"cover/{bool(value['included'])}"] += 1


def required_forms(*_):
    return {*(f"placement/{placement}" for placement in laws.PLACEMENTS), "class/omitted", "class/qualified-text-ops",
            *(f"route/{route}" for route in (*laws.ANALYSIS, laws.DIRECT)), "owner/collated_app", "owner/pg_temp",
            "kind-mix/True", "kind-mix/False", "cover/True", "cover/False"}


def controls():
    return {}


def fault_probe(item, response, db):
    native = laws.statement(item)
    if item.get("offProfile") is not None:
        if rejects(response, TYPE):
            return
        check_value(item, response)
        actual = db.request(observer_request(item, native))
        rendered = db.request(observer_request(item, response["sql"]))
        same(laws.expected(item, native_original=True), actual)
        check_observation(item, rendered, response["sql"])
        require(actual["collations"] != rendered["collations"], "postgres-btree-declared-fault-observation")
        raise Failure(ASSUMED, {"sql": response["sql"], "original": actual, "observed": rendered})
    if rejects(response, TYPE):
        actual = db.request(observer_request(item, native))
        check_observation(item, actual, native)
        raise Failure(LOST, {"sql": native, "observed": actual, "boundary": response})
    check_value(item, response)
    check_observation(item, db.request(observer_request(item, response["sql"])), response["sql"])


def replay_fault(rule, record):
    item, witness = rule.item, record["detail"]
    if rule.signal == LOST:
        check_value(item, record["healthyResponse"])
        require(rejects(record["mutantResponse"], TYPE), LOST)
        same(laws.statement(item), witness["sql"])
        same(record["mutantResponse"], witness["boundary"])
        check_observation(item, witness["observed"], witness["sql"])
        return
    require(rejects(record["healthyResponse"], TYPE), ASSUMED)
    check_value(item, record["mutantResponse"])
    same(record["mutantResponse"]["sql"], witness["sql"])
    same(laws.expected(item, native_original=True), witness["original"])
    check_observation(item, witness["observed"], witness["sql"])
    require(witness["original"]["collations"] != witness["observed"]["collations"], ASSUMED)


def faults(_, tag):
    prefix = "sql-semantics-postgres/src/"
    copy = laws.case(tag, "like-copy", "one-text-key", laws.ORDERS[0], laws.FORMS[0], laws.ANALYSIS[0])
    neighbor = laws.off_profile(tag, "neighbor")
    posix = laws.off_profile(tag, "posix")
    binding = prefix + "Ir/Schema/Index/Btree/IndexOperands.php"
    return [
        RenderFault("lose-declared-collation-through-table-copy", prefix + "Rules/Table/Definition/Declarations.php",
                    "$identity && $column->identity, $column->collation);", "$identity && $column->identity, null);",
                    copy, LOST, laws.analyzed(copy)),
        RenderFault("bind-collation-from-neighboring-slot", binding,
                    "$table->columnCollations[$slot] ?? null", "$table->columnCollations[2] ?? null",
                    neighbor, ASSUMED, laws.analyzed(neighbor)),
        RenderFault("assume-stock-c-for-unknown-column", binding,
                    "$table->columnCollations[$slot] ?? null", "$table->columnCollations[$slot] ?? TextCollation::C",
                    posix, ASSUMED, laws.analyzed(posix)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
