"""Compare resolved stock and shadowed PostgreSQL collation and operator-class selectors."""

import json
from itertools import product
import sys

import postgres_stock_lookup_cases as laws
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_join import main
from run_server_postgres_create_btree import check_preparation, observation, prepare, specifications as profiles
from sqlite_domain import RenderFault

LABEL = "postgres-stock-lookup"
OBSERVER = "postgres_stock_lookup_observer.py"
CONTRACT_NAME = "postgres-stock-collation-lookup-evidence-v1.json"
CONTRACT = "8916aa3803118f2c70c73ec0577541e342419e82a60d691d64da1c9f1294456f"
MEANING = "meaning-mismatch"
ADMITTED = "postgres-stock-lookup-admitted-shadow"
REJECTED = "postgres-stock-lookup-rejected-language-input"
COMPARATOR_FAULTS = {"Accept a changed native operator class identity": "wrongOperatorClass"}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-stock-lookup-contract")
    spec = json.loads(path.read_text())
    reference = spec["reference"]
    require(digest(TOOL / reference["file"]) == reference["sha256"]
            and spec["finite"]["allowedExclusions"] == spec["exploration"]["allowedExclusions"] == [],
            "postgres-stock-lookup-reference")
    return spec, profiles()[1]


def finite_coords(spec):
    finite = list(laws.coords(spec["finite"]))
    require(len(finite) == len(set(finite)) == spec["finite"]["coordinates"] and set(finite) == laws.identities(),
            "postgres-stock-lookup-finite-coordinate-set")
    return finite


def finite_ids(_, spec, _tag):
    admitted = {coordinate for coordinate in finite_coords(spec) if not laws.shadowed(coordinate)}
    require(len(admitted) == spec["finite"]["admitted"], "postgres-stock-lookup-finite-positive-count")
    return admitted


def direct_finite_ids(*_):
    return set(product(laws.ROLES, laws.SCOPES))


def rejected_finite_ids(spec, _):
    shadow = {coordinate for coordinate in finite_coords(spec) if laws.shadowed(coordinate)}
    require(len(shadow) == spec["finite"]["shadowedUnqualified"], "postgres-stock-lookup-finite-shadow-count")
    return shadow | {("explicit-stock-first", role, "standalone", "outside-stock") for role in laws.ROLES}


def analysis_finite_id(_, response, item, command):
    same(laws.sql(item), command)
    check_value(item, response)
    return tuple(item["coordinate"])


def finite_id(_, response, item):
    check_value(item, response)
    return tuple(item["coordinate"][1:3])


def direct_finite_id(engine, response, item, request):
    same(laws.direct(item), request)
    return finite_id(engine, response, item)


def requests(engine, tag, spec):
    for number, coordinate in enumerate(finite_coords(spec)):
        item = laws.case(tag, *coordinate)
        if laws.shadowed(coordinate):
            yield f"negative/{number}", laws.analysis(item), item, laws.guard(item)
        else:
            yield f"finite/{number}", laws.analysis(item), item, None
    for number, (role, scope) in enumerate(sorted(direct_finite_ids(engine, spec, tag))):
        item = laws.case(tag, "temporary-namesake", role, scope, "stock-qualified")
        yield f"direct/{number}", laws.direct(item), item, None
    for role in laws.ROLES:
        item = laws.case(tag, "explicit-stock-first", role, "standalone", "outside-stock")
        yield f"outside/{role}", laws.analysis(item), item, laws.guard(item)
    exploration = spec["exploration"]
    for seed in exploration["seeds"]:
        for number, item in enumerate(laws.generated(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{number}", laws.analysis(item), item, None
        for number, item in enumerate(laws.generated(tag, seed + 1, exploration["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{number}", laws.direct(item), item, None


def check_value(item, response):
    role, scope = item["coordinate"][1:3]
    profile = item["tag"] + "/utf8/standard-conforming-strings/native"
    if role == "column-collation" and scope == "standalone":
        profile += "/heap"
    expected = laws.graph(item)
    require(response["status"] == "constructed" and response["profile"] == profile
            and expected == {key: response.get(key) for key in expected}, "postgres-stock-lookup-graph",
            {"expected": expected, "response": response})
    same(laws.sql(item, canonical=True), response["sql"])


def observer_request(item, command):
    return {"trial": item, "sql": command}


def compare(item, actual, command, source):
    expected = {"status": "postgres-stock-lookup-observation", **laws.expected(item, source)}
    witness = {"sql": command, "observed": actual, "expected": expected}
    require(actual["status"] == expected["status"], "postgres-stock-lookup-observer-result", witness)
    require(actual == expected, MEANING, witness)


def check_observation(item, actual, command):
    compare(item, actual, command, source=True)


def observe_rejection(_, item, actual, command):
    compare(item, actual, command, source=True)
    source = laws.expected(item)
    resolved = laws.expected(item, source=False)
    require(any(source[key] != resolved[key] for key in ("fields", "index")), "postgres-stock-lookup-indistinguishable")
    return tuple(item["coordinate"])


def sample_forms(forms, response, _, item):
    role, scope = item["coordinate"][1:3]
    if "create" in response:
        columns = response["create"]["columns"]
        key_slot, = (slot for slot, column in enumerate(columns) if len(column) == 5)
    elif "createSchema" in response:
        columns = response["schemaTables"][0]["columns"]
        key_slot = response["schemaIndexes"][0]["keys"][0][0]
    else:
        columns = None
        key_slot = response["createBtree"]["keys"][0][0]
    forms[f"scope/{scope}"] += 1
    forms[f"role/{role}"] += 1
    forms[f"key/{key_slot}"] += 1
    if columns is not None:
        forms[f"width/{len(columns)}"] += 1


def sample_native_forms(forms, actual, _, item):
    forms[f"path/{item['coordinate'][0]}"] += 1
    forms[f"native-class/{actual['index'][3][0]}"] += 1
    forms[f"native-field-count/{len(actual['fields'])}"] += 1


def required_forms(*_):
    return ({f"path/{path}" for path in laws.PATHS} | {f"role/{role}" for role in laws.ROLES}
            | {f"scope/{scope}" for scope in laws.SCOPES} | {f"key/{slot}" for slot in range(6)}
            | {f"native-field-count/{width}" for width in range(2, 7)} | {"native-class/pg_catalog"})


def controls():
    item = laws.case("control", "temporary-namesake", "text-operator-class", "standalone", "unqualified")
    correct = {"status": "postgres-stock-lookup-observation", **laws.expected(item)}
    changed = {**correct, "index": [*correct["index"][:3], ["hidden_names", "text_ops"]]}
    check_observation(item, correct, laws.sql(item))
    try:
        check_observation(item, changed, laws.sql(item))
    except Failure as error:
        same(MEANING, error.signal)
        return {"wrongOperatorClass": error.detail}
    raise Failure("postgres-stock-lookup-comparator-missed-different-class")


def fault_probe(item, response, db):
    command = laws.sql(item)
    source = db.request(observer_request(item, command))
    check_observation(item, source, command)
    if laws.shadowed(item["coordinate"]):
        if rejects(response, laws.guard(item)):
            return
        check_value(item, response)
        emitted = response["sql"]
        observed = db.request(observer_request(item, emitted))
        compare(item, observed, emitted, source=False)
        observe_rejection(None, item, source, command)
        raise Failure(ADMITTED, {"sql": emitted, "observed": observed, "source": source})
    if rejects(response, laws.guard(item)):
        raise Failure(REJECTED, {"sql": command, "observed": source})
    check_value(item, response)
    emitted = response["sql"]
    check_observation(item, db.request(observer_request(item, emitted)), emitted)


def replay_fault(rule, record):
    item, witness = rule.item, record["detail"]
    healthy, mutant = record["healthyResponse"], record["mutantResponse"]
    if rule.signal == REJECTED:
        check_value(item, healthy)
        require(rejects(mutant, laws.guard(item)), REJECTED)
        same(laws.sql(item), witness["sql"])
        check_observation(item, witness["observed"], witness["sql"])
        return
    require(rejects(healthy, laws.guard(item)), ADMITTED)
    check_value(item, mutant)
    same(mutant["sql"], witness["sql"])
    compare(item, witness["observed"], witness["sql"], source=False)
    observe_rejection(None, item, witness["source"], laws.sql(item))


def faults(_, tag):
    base = "sql-semantics-postgres/src/"
    lookup = base + "Model/Contract/Lookup/StockLookup.php"
    earlier = laws.case(tag, "earlier-application", "index-collation", "schema-member", "unqualified")
    temporary = laws.case(tag, "temporary-namesake", "text-operator-class", "standalone", "unqualified")
    declaring = laws.case(tag, "earlier-application", "column-collation", "standalone", "unqualified")
    return [
        RenderFault("skip-earlier-application-namespace", lookup,
                    "return $schema->value === 'pg_catalog';", "return true;", earlier, ADMITTED, laws.analysis(earlier)),
        RenderFault("treat-current-temp-as-blocker", lookup,
                    "if ($schema->value !== 'pg_temp') {", "if (true) {", temporary, REJECTED, laws.analysis(temporary)),
        RenderFault("use-stock-default-after-declaration", base + "Ir/Schema/CreateColumnLowering.php",
                    "StockLookup::firstCatalog($operation->context->searchPath)", "true", declaring, ADMITTED,
                    laws.analysis(declaring)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
