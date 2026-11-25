"""Verify PostgreSQL table and schema effects with explicit stock text collations."""

import json
import sys

import postgres_collated_table_cases as laws
from postgres_create_btree_cases import refusal
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_join import main
from run_server_postgres_create_btree import check_preparation, observation, prepare, specifications as profiles
from sqlite_domain import RenderFault

LABEL = "postgres-collated-table"
OBSERVER = "postgres_collated_table_observer.py"
CONTRACT_NAME = "postgres-create-collated-text-evidence-v1.json"
CONTRACT = "d0d16ec894e2767b86e3cfd5eb1cd4fc18e1ff498c8f8ad52eb1e18d0167c4ae"
TYPE = refusal("A text collation requires native text storage.")
STOCK = refusal("The column requires a resolved stock PostgreSQL collation.")
MISSING = refusal("A B-tree text key requires its resolved collation, and other keys cannot declare one.")
MEANING = "meaning-mismatch"
ADMITTED = "postgres-collated-table-unchecked-construction"
RESOLUTION = "postgres-collated-table-unchecked-resolution"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-collated-table-contract")
    scope = json.loads(path.read_text())
    source = scope["reference"]
    require(digest(TOOL / source["file"]) == source["sha256"] and scope["exploration"]["allowedExclusions"] == [],
            "postgres-collated-table-contract-reference")
    return scope, profiles()[1]


def finite_ids(_, spec, _tag):
    fixed, finite = laws.identities(), spec["finite"]
    require(set(laws.coords(finite)) == fixed and len(fixed) == finite["coordinates"],
            "postgres-collated-table-finite-domain")
    return fixed


def finite_id(_, response, item):
    check_value(item, response)
    return tuple(item["coordinate"])


def analysis_finite_id(engine, response, item, command):
    same(laws.sql(item), command)
    return finite_id(engine, response, item)


def direct_finite_id(engine, response, item, request):
    same(laws.direct(item), request)
    return finite_id(engine, response, item)


def negatives(tag):
    base = laws.case(tag, "standalone", "one-text", "omitted", "nullable")
    integer = {**base, "codeKind": "INTEGER", "faultRole": "storage"}
    posix = {**base, "collation": "POSIX", "faultRole": "resolve"}
    yield laws.direct(integer), TYPE
    yield laws.analyzed(integer), TYPE
    yield laws.analyzed(posix), STOCK
    composed = laws.case(tag, "schema-index", "adjacent-text", "omitted", "nullable")
    request = laws.analyzed(composed)
    command = request["sql"].replace('"orders" ("code")', '"orders" ("payload")')
    require(command != request["sql"], "postgres-collated-table-refusal-construction")
    yield {**request, "sql": command}, MISSING


def requests(_, tag, spec):
    for ordinal, axes in enumerate(laws.coords(spec["finite"])):
        item = laws.case(tag, *axes)
        yield f"finite/{ordinal}", laws.analyzed(item), item, None
        yield f"direct/{ordinal}", laws.direct(item), item, None
    exploration = spec["exploration"]
    for seed in exploration["seeds"]:
        for ordinal, item in enumerate(laws.generated(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", laws.analyzed(item), item, None
        for ordinal, item in enumerate(laws.generated(tag, seed + 1, exploration["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{ordinal}", laws.direct(item), item, None
    for ordinal, (request, guard) in enumerate(negatives(tag)):
        yield f"negative/{ordinal}", request, None, guard


def check_value(item, response):
    fields = laws.graph(item)
    profile = item["tag"] + "/utf8/standard-conforming-strings/native"
    if item["scope"] == "standalone":
        profile += "/heap"
    require(response["status"] == "constructed" and response["profile"] == profile
            and fields == {key: response[key] for key in fields}, "postgres-collated-table-value",
            {"expected": fields, "actual": response})


def observer_request(item, command):
    return {"trial": item, "sql": command}


def check_observation(item, observed, command):
    predicted = laws.expected(item)
    witness = {"expected": predicted, "observed": observed, "sql": command}
    require(observed["status"] == "postgres-collated-table-observation", "postgres-collated-table-observer", witness)
    require(predicted == observed, MEANING, witness)


def sample_forms(forms, response, _, item):
    columns = response["create"]["columns"] if item["scope"] == "standalone" else response["schemaTables"][0]["columns"]
    (slot, field), = ((index, column) for index, column in enumerate(columns) if column[-1] == "C")
    forms[f"slot/{slot}"] += 1
    forms[f"scope/{item['scope']}"] += 1
    forms[f"nullable/{field[2]}"] += 1
    forms[f"default/{None if field[3] is None else field[3][0]}"] += 1
    forms[f"width/{len(columns)}"] += 1


def sample_native_forms(forms, actual, _, _item):
    forms[f"nativeProbe/{actual['defaultStatus']}"] += 1
    forms[f"nativeIndex/{actual['index'] is not None}"] += 1


def required_forms(*_):
    return {"slot/0", "slot/1", "slot/2", "scope/standalone", "scope/schema-index", "nullable/True", "nullable/False",
            "default/None", "default/null", "default/text", "width/2", "width/3",
            "nativeProbe/23502", "nativeProbe/00000", "nativeIndex/True", "nativeIndex/False"}


def controls():
    return {}


def native_refusal(observed):
    require(observed["status"] == "postgres-collated-table-native-refusal" and observed["result"]["sqlState"] == "42804"
            and observed["ownerAfterRollback"] is True, "postgres-collated-table-unrelated-native-error", observed)


def fault_probe(item, response, db):
    role = item.get("faultRole")
    if role == "storage" and rejects(response, TYPE) or role == "resolve" and rejects(response, STOCK):
        return
    check_value(item, response)
    command = response["sql"]
    observed = db.request(observer_request(item, command))
    if role == "storage":
        native_refusal(observed)
        raise Failure(ADMITTED, {"sql": command, "observed": observed})
    if role == "resolve":
        native = db.request(observer_request(item, laws.sql(item)))
        same(laws.expected(item, source=True), native)
        check_observation(item, observed, command)
        require(native["metadata"] != observed["metadata"], RESOLUTION)
        raise Failure(RESOLUTION, {"sql": command, "observed": observed, "native": native})
    check_observation(item, observed, command)


def replay_fault(rule, record):
    item, witness = rule.item, record["detail"]
    healthy, mutant = record["healthyResponse"], record["mutantResponse"]
    check_value(item, mutant)
    same(mutant["sql"], witness["sql"])
    if rule.signal == ADMITTED:
        require(rejects(healthy, TYPE), ADMITTED)
        native_refusal(witness["observed"])
        return
    if rule.signal == RESOLUTION:
        require(rejects(healthy, STOCK), RESOLUTION)
        same(laws.expected(item, source=True), witness["native"])
        check_observation(item, witness["observed"], witness["sql"])
        require(witness["native"]["metadata"] != witness["observed"]["metadata"], RESOLUTION)
        return
    check_value(item, healthy)
    try:
        check_observation(item, witness["observed"], witness["sql"])
    except Failure as error:
        same(MEANING, error.signal)
        same(witness, error.detail)
        same(["pg_catalog", "default"], witness["observed"]["metadata"][item["codeSlot"]][3])
        return
    raise Failure("postgres-collated-table-fault-not-reproduced")


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/Schema/"
    good = laws.case(tag, "schema-index", "adjacent-text", "quoted-text", "required")
    base = laws.case(tag, "standalone", "one-text", "omitted", "nullable")
    integer = {**base, "codeKind": "INTEGER", "faultRole": "storage"}
    unresolved = {**base, "collation": "POSIX", "faultRole": "resolve"}
    return [
        RenderFault("omit-column-collation", root + "TableColumn.php",
                    """($this->collation === null ? '' : ' COLLATE pg_catalog."' . $this->collation->value . '"')""",
                    "''", good, MEANING, laws.direct(good)),
        RenderFault("assume-column-collation-is-c", root + "CreateColumnLowering.php",
                    "DeclaredTextCollation::resolve($qualifier->collation->qualified()) === DeclaredTextCollation::StockC",
                    "true", unresolved, RESOLUTION, laws.analyzed(unresolved)),
        RenderFault("accept-numeric-column-with-text-collation", root + "TableColumn.php",
                    "$collation === null || $storage === CharacterStorage::Text", "true", integer, ADMITTED, laws.direct(integer)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
