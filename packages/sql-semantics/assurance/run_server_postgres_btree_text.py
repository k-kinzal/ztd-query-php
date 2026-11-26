"""Verify text-role construction and native PostgreSQL C B-tree observations."""

from copy import deepcopy
import json
import sys

from postgres_btree_text_cases import analyzed, case, coordinates, direct, expected, generated, graph, identities, statement
from postgres_create_btree_cases import refusal
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main
from run_server_postgres_create_btree import check_preparation, observation, prepare, specifications as btree_profiles
from sqlite_domain import RenderFault

LABEL = "postgres-btree-text"
OBSERVER = "postgres_btree_text_observer.py"
CONTRACT_NAME = "postgres-btree-native-text-evidence-v1.json"
CONTRACT = "23cf24481618b03f5b3b5159b2ea7c507fab8337fa35d9fc906748548c21c06b"
MEANING = "meaning-mismatch"
TYPE = refusal("A B-tree text key requires its resolved collation, and other keys cannot declare one.")
STOCK = refusal("An explicitly collated PostgreSQL B-tree key requires stock pg_catalog.C and its default text operator class.")
COMPARATOR_FAULTS = {"Ignore a native collation coordinate when comparing": "wrong-native-collation"}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-text-btree-contract")
    scope = json.loads(path.read_text())
    reference = scope["reference"]
    require(digest(TOOL / reference["file"]) == reference["sha256"] and scope["exploration"]["allowedExclusions"] == [],
            "postgres-text-btree-reference")
    return scope, btree_profiles()[1]


def finite_ids(_, spec, _tag):
    fixed = identities()
    require(set(coordinates(spec["finite"])) == fixed and len(fixed) == spec["finite"]["coordinates"],
            "postgres-text-btree-finite")
    return fixed


def finite_id(_, response, item):
    check_value(item, response)
    return tuple(item["coordinate"])


def analysis_finite_id(engine, response, item, command):
    same(statement(item, original=True), command)
    return finite_id(engine, response, item)


def direct_finite_id(engine, response, item, request):
    same(direct(item), request)
    return finite_id(engine, response, item)


def refusals(tag):
    item = case(tag, "text-first", "ASC NULLS LAST", "plain", "omitted")
    raw, request = direct(item), analyzed(item)
    binding = raw["createBtree"]
    yield {**raw, "createBtree": {**binding, "keys": [[2, "ASC NULLS LAST"]]}}, TYPE
    yield {**raw, "createBtree": {**binding, "keys": [[1, "ASC NULLS LAST", "C"]]}}, TYPE
    yield {**request, "sql": request["sql"].replace('pg_catalog."C"', 'pg_catalog."POSIX"')}, STOCK
    yield {**request, "sql": request["sql"].replace('pg_catalog."C" ASC', 'pg_catalog."C" pg_catalog.text_pattern_ops ASC')}, STOCK
    yield {**request, "sql": request["sql"].replace('"code" COLLATE', '"bucket" COLLATE')}, TYPE


def requests(_, tag, spec):
    for ordinal, coordinate in enumerate(coordinates(spec["finite"])):
        item = case(tag, *coordinate)
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", direct(item), item, None
    exploration = spec["exploration"]
    for seed in exploration["seeds"]:
        for ordinal, item in enumerate(generated(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", analyzed(item), item, None
        for ordinal, item in enumerate(generated(tag, seed + 1, exploration["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{ordinal}", direct(item), item, None
    for number, (request, boundary) in enumerate(refusals(tag)):
        yield f"negative/{number}", request, None, boundary


def check_value(item, response):
    predicted, profile = graph(item), item["tag"] + "/utf8/standard-conforming-strings/native"
    require(response["status"] == "constructed" and response["profile"] == profile and response["createBtree"] == predicted,
            "postgres-text-btree-value", {"expected": predicted, "actual": response})


def observer_request(item, command):
    return {"sql": command, "trial": item}


def check_observation(item, actual, command):
    prediction = expected(item)
    witness = {"sql": command, "expected": prediction, "observed": actual}
    require(actual["status"] == "postgres-text-btree", "postgres-text-btree-observer", witness)
    require(actual == prediction, MEANING, witness)


def sample_forms(forms, response, _, item):
    root = response["createBtree"]
    fields = {slot: kind for slot, _, kind in root["bindings"]}
    roles = [slot for slot, *_ in root["keys"]]
    forms[f"text-key/{any(fields[slot] == 'text' for slot in roles)}"] += 1
    forms[f"repeated/{len(set(roles)) < len(roles)}"] += 1
    forms[f"text-include/{any(fields[slot] == 'text' for slot in root['included'])}"] += 1
    predicate = root["predicate"]
    forms[f"text-predicate/{predicate is not None and fields[predicate[0]] == 'text'}"] += 1
    for _, order, *_ in root["keys"]:
        forms[f"order/{order}"] += 1


def sample_native_forms(forms, actual, _, _item):
    for collation in actual["collations"]:
        forms[f"native-C/{collation == ['pg_catalog', 'C']}"] += 1
    for outcome in actual["writes"]:
        forms[f"native-write/{outcome}"] += 1


def required_forms(*_):
    return {"text-key/True", "text-key/False", "repeated/True", "repeated/False", "text-include/True", "text-predicate/True",
            "native-C/True", "native-C/False", "native-write/23505", "native-write/00000",
            "order/ASC NULLS LAST", "order/ASC NULLS FIRST", "order/DESC NULLS LAST", "order/DESC NULLS FIRST"}


def controls():
    item = case("pg-17.2", "text-first", "ASC NULLS LAST", "plain", "omitted")
    healthy = expected(item)
    command = statement(item)
    check_observation(item, healthy, command)
    wrong = deepcopy(healthy)
    wrong["collations"][0][1] = "POSIX"
    try:
        check_observation(item, wrong, command)
    except Failure as error:
        same(MEANING, error.signal)
        return {"wrong-native-collation": {"input": item, "witness": error.detail}}
    raise Failure("postgres-text-btree-comparator-accepted")


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same("postgres-text-btree", witness["observed"]["status"])


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/Index/Btree/CreateIndex.php"
    without_c = case(tag, "text-first", "ASC NULLS LAST", "plain", "stock-qualified")
    wrong_slot = case(tag, "two-text-keys", "DESC NULLS FIRST", "plain", "stock-qualified")
    return [
        RenderFault("lose-explicit-text-index-collation", path,
                    """' COLLATE pg_catalog."' . $key->collation->value . '" pg_catalog.text_ops'""",
                    "' pg_catalog.text_ops'", without_c, MEANING, direct(without_c)),
        RenderFault("use-collated-index-key-at-another-slot", path, "$this->bindings[$key->slot]->name->quoted()",
                    "$this->bindings[3]->name->quoted()", wrong_slot, MEANING, direct(wrong_slot)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
