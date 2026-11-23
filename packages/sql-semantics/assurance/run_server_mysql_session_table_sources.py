"""Observe MySQL session-local and permanent declarations through the same native row operations."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import rejects, same
from run_server_join import main, probe
from run_server_mysql_ordered_table_rename import DIAGNOSTIC, SEMANTIC, specifications as profiles
from server_join_cases import compare, literal, slot
from sqlite_domain import RenderFault

LABEL = "mysql-session-table-sources"
OBSERVER = "mysql_session_table_sources_observer.php"
CONTRACT_NAME = "mysql-session-table-sources-v1.json"
CONTRACT = "95d14286022d773b97560ef85af6e4fd761877169fbe28e5234e80a4941e7e9d"
FAULT_REQUESTS_CONTRACT = "cdedffddeddc30e3884f0140eeee49c18d9c3b4a288f2c0b26866ee440ec12fd"
COMPLETE_SEEDS = True
SCHEMA, DECOY = "ir_fixture", "ir_session_decoy"
TABLE, RENAMED = "ir_session_data", "ir_session_shifted"
AXES = ("fixture", "operation", "value")
STRUCTURE = "mysql-session-binding-mismatch"
PERSISTENT_BODY = "A checked view reads one persistent base table or an independent literal projection."


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-session-table-contract-mismatch")
    require(digest(TOOL / "mysql-session-table-fault-requests-v1.json") == FAULT_REQUESTS_CONTRACT,
            "mysql-session-fault-requests-contract-mismatch")
    return json.loads(path.read_text()), profiles()[1]


def case(tag, fixture, operation, value=0, variant=0):
    return {"dialect": "mysql", "release": tag, "fixture": fixture, "operation": operation, "value": value, "variant": variant}


def scope(item):
    return DECOY if item["fixture"] == "cross-schema-shadowed" else SCHEMA


def session_source(item):
    result = {"schema": scope(item), "table": TABLE, "columns": ["n"]}
    if item["fixture"] != "persistent":
        result["relationKind"] = "temporary-table"
    return result


def select(source, columns, predicate=None):
    return {"source": source, "columns": columns, "where": predicate, "distinct": False, "orderBy": [], "limit": None}


def rename_moves():
    origin = [SCHEMA, TABLE]
    return [{"from": origin, "to": [SCHEMA, RENAMED], "origin": origin}]


def statement(item):
    name = session_source(item)
    value = literal(item["value"])
    predicate = compare(">", slot(0), value)
    operation = item["operation"]
    if operation == "rename":
        return {"tableRename": rename_moves()}
    if operation == "query":
        return select(name, [[slot(0), "value"], [slot(0), "again"]], predicate)
    if operation == "insert-values":
        return {"target": name, "rows": [[str(item["value"])]]}
    if operation == "insert-query":
        return {"target": name, "insertQuery": {**select(None, [[value, "p0"]]), "dialect": "mysql", "release": item["release"]}}
    if operation == "update":
        return {"target": name, "assignments": [[slot(0), value]], "where": predicate}
    if operation == "delete":
        return {"target": name, "where": predicate}
    raise Failure("mysql-session-unregistered-operation", operation)


def direct(item):
    return {"dialect": item["dialect"], "release": item["release"], **statement(item)}


def finite_ids(_, spec, _tag):
    grid = set(product(*(spec["finite"][axis] for axis in AXES)))
    require(len(grid) == spec["finite"]["identities"], "mysql-session-grid-size")
    return grid


def analyzed_request(item, sql, *, declarations=None, complete=True):
    fixture = item["fixture"]
    if declarations is None:
        main = f"CREATE TABLE {SCHEMA}.{TABLE} (n int)"
        decoy = f"CREATE TABLE {DECOY}.{TABLE} (n int)"
        temporary = f"CREATE TEMPORARY TABLE {scope(item)}.{TABLE} (n int)"
        declarations = ([] if fixture == "temporary-only" else [main]) + [decoy]
        if fixture != "persistent":
            declarations.append(temporary)
    return {"dialect": "mysql", "release": item["release"], "currentDatabase": SCHEMA,
            "sql": sql, "declarations": declarations, "complete": complete}


def query_sql(table, value, qualified=False):
    second = f"{TABLE}.n" if qualified else "n"
    return f"SELECT n AS value, {second} AS again FROM {table} WHERE n > {value}"


def analyzed(tag):
    yield query_sql(TABLE, -1), case(tag, "persistent", "query", -1)
    yield query_sql(f"{SCHEMA}.{TABLE}", 2), case(tag, "temporary-only", "query", 2)
    yield query_sql(TABLE, 2, qualified=True), case(tag, "shadowed", "query", 2)
    yield f"INSERT INTO {TABLE} (n) VALUES (5)", case(tag, "shadowed", "insert-values", 5)
    yield f"INSERT INTO {TABLE} (n) SELECT 5 AS p0", case(tag, "shadowed", "insert-query", 5)
    yield f"UPDATE {TABLE} SET n = 2 WHERE n > 2", case(tag, "shadowed", "update", 2)
    yield f"DELETE FROM {TABLE} WHERE n > -1", case(tag, "shadowed", "delete", -1)
    yield query_sql(f"{DECOY}.{TABLE}", -1), case(tag, "cross-schema-shadowed", "query", -1)
    yield f"RENAME TABLE {TABLE} TO {RENAMED}", case(tag, "shadowed", "rename")


def negatives(tag):
    item = case(tag, "shadowed", "query", -1)
    query = query_sql(TABLE, -1)
    temporary = f"CREATE TEMPORARY TABLE {SCHEMA}.{TABLE} (n int)"
    yield analyzed_request(item, query, declarations=[temporary, temporary]), SEMANTIC, "has conflicting declarations"
    yield analyzed_request(item, query_sql("ir_session_missing", 0)), SEMANTIC, "does not exist"
    yield analyzed_request(item, query_sql("ir_session_missing", 0), complete=False), DIAGNOSTIC, "requires a resolved, complete local relation"
    view_query = {**select(session_source(item), [[slot(0), "n"]]), "dialect": "mysql", "release": tag}
    view = {"dialect": "mysql", "release": tag, "createView": {
        "target": [SCHEMA, "ir_session_view"], "query": view_query, "columns": ["n"],
        "algorithm": "UNDEFINED", "policy": "require-new", "security": "DEFINER",
    }}
    yield view, DIAGNOSTIC, PERSISTENT_BODY
    delete = direct(case(tag, "persistent", "delete"))
    delete["target"] = {**delete["target"], "relationKind": "view"}
    yield delete, DIAGNOSTIC, "A DELETE target must be a base table."
    invalid = direct(item)
    invalid["columns"] = [[slot(2), "value"]]
    yield invalid, DIAGNOSTIC, "slot"
    local_rename = case(tag, "temporary-only", "rename")
    yield analyzed_request(local_rename, f"RENAME TABLE {TABLE} TO {RENAMED}"), DIAGNOSTIC, "requires a resolved persistent base-table origin"


def seeded(tag, spec, seed):
    rng = Random(seed)
    for _ in range(spec["exploration"]["casesPerSeed"]):
        yield case(tag, rng.choice(spec["finite"]["fixture"]), rng.choice(spec["finite"]["operation"]),
                   rng.randint(-37, 37), rng.randint(0, 40))


def requests(_, tag, spec):
    for index, (fixture, operation, value) in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = case(tag, fixture, operation, value)
        yield f"finite/{index}", direct(item), item, None
    written = list(analyzed(tag))
    require(len(written) == len(spec["analysis"]) + 1, "mysql-session-authored-size")
    for index, (sql, item) in enumerate(written):
        yield f"analyze/{index}", analyzed_request(item, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, spec, seed)):
            yield f"seed/{seed}/{index}", direct(item), item, None
    bad = list(negatives(tag))
    require(len(bad) == len(spec["negative"]), "mysql-session-rejections-size")
    for index, (request, exception, message) in enumerate(bad):
        yield f"negative/{index}", request, None, {"exception": exception, "message": message}


def bag(rows):
    return sorted(rows, key=json.dumps) if rows is not None else None


def initial(fixture, variant):
    global_main = None if fixture == "temporary-only" else bag([-4 - variant, 2, 8 + variant, None])
    permanent = {SCHEMA: global_main, DECOY: bag([30 + variant, None]), "renamed": None}
    visible = dict(permanent)
    if fixture != "persistent":
        schema = DECOY if fixture == "cross-schema-shadowed" else SCHEMA
        visible[schema] = bag([-2 - variant, 2, 2, 4 + variant, None])
    return {"permanent": permanent, "visible": visible, "marker": [0], "committedMarker": [0]}


def prediction(item):
    before = initial(item["fixture"], item["variant"])
    operation, value = item["operation"], item["value"]
    visible = dict(before["visible"])
    permanent = dict(before["permanent"])
    schema = scope(item)
    records = visible[schema]
    accepted = lambda n: n is not None and n > value
    matching = [n for n in records if accepted(n)]
    active = operation != "rename"
    if operation == "query":
        result = {"kind": "query", "fields": [["value", "LONG"], ["again", "LONG"]], "rows": bag([[n, n] for n in matching])}
    elif operation in ("insert-values", "insert-query"):
        visible[schema] = bag([*records, value])
        result = {"kind": "effect", "count": 1}
    elif operation == "update":
        visible[schema] = bag([value if accepted(n) else n for n in records])
        result = {"kind": "effect", "count": len(matching)}
    elif operation == "delete":
        visible[schema] = bag([n for n in records if not accepted(n)])
        result = {"kind": "effect", "count": len(matching)}
    elif operation == "rename":
        permanent["renamed"] = permanent[SCHEMA]
        permanent[SCHEMA] = None
        visible["renamed"] = permanent["renamed"]
        result = {"kind": "effect", "count": 0}
    else:
        raise Failure("mysql-session-unknown-operation", item)
    effect = {"visible": visible, "permanent": permanent, "marker": [0, 7], "committedMarker": [0] if active else [0, 7]}
    return {"status": "mysql-session-effect", "initial": before, "result": result,
            "effect": effect, "active": active, "restored": before if active else effect}


def normalize(state):
    return {**state, **{space: {name: bag(rows) for name, rows in state[space].items()} for space in ("permanent", "visible")}}


def observation(raw):
    return raw


def observed_effect(raw):
    result = raw["result"]
    if result["kind"] == "query":
        result = {**result, "rows": bag(result["rows"])}
    return {**raw, "result": result, **{phase: normalize(raw[phase]) for phase in ("initial", "effect", "restored")}}


def observed_statement(item, response):
    expected = statement(item)
    if item["operation"] == "insert-query":
        del expected["insertQuery"]["release"], expected["insertQuery"]["dialect"]
    actual = {name: value for name, value in response.items() if name not in {"status", "sql", "profile"}}
    return expected, actual


def check_value(item, response):
    profile = item["release"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native"
    if item["operation"] in ("query", "insert-query"):
        profile += "/materialized" if item["release"] == "mysql-5.6.51" else "/derived-merge=on"
    require(response["status"] == "constructed" and response["profile"] == profile, "mysql-session-positive-rejected", response)
    expected, actual = observed_statement(item, response)
    require(expected == actual, STRUCTURE, {"sql": response["sql"], "expected": expected, "observed": actual})


def finite_id(_, reply, item):
    response_source = reply["source"] if "source" in reply else reply["target"]
    temporary = response_source.get("relationKind") == "temporary-table"
    if temporary and response_source["schema"] == DECOY:
        fixture = "cross-schema-shadowed"
    elif temporary:
        fixture = "temporary-only" if item["fixture"] == "temporary-only" else "shadowed"
    else:
        fixture = "persistent"
    if "source" in reply:
        operation, value = "query", reply["where"][3][1]
    elif "rows" in reply:
        operation, value = "insert-values", reply["rows"][0][0]
    elif "insertQuery" in reply:
        operation, value = "insert-query", reply["insertQuery"]["columns"][0][0][1]
    elif "assignments" in reply:
        operation, value = "update", reply["assignments"][0][1][1]
    else:
        operation, value = "delete", reply["where"][3][1]
    return fixture, operation, int(value)


def observer_request(item, sql):
    return {"sql": sql, "fixture": item["fixture"], "variant": item["variant"]}


def prepare(_, db):
    return {"initial": db.environment["initial"]}


def check_preparation(_, environment, preparation):
    same(environment["initial"], preparation["initial"])
    same(initial("shadowed", 0), normalize(environment["initial"]))


def check_observation(item, actual, sql):
    expected = prediction(item)
    detail = {"sql": sql, "expected": expected, "observed": actual}
    require(actual["status"] == "mysql-session-effect", "mysql-session-observer-rejected", detail)
    try:
        same(expected, observed_effect(actual))
    except Failure as error:
        raise Failure(error.signal, detail) from error


def sample_forms(forms, reply, _, item):
    fixture, operation, value = finite_id(None, reply, item)
    forms[f"fixture/{fixture}"] += 1
    forms[f"operation/{operation}"] += 1
    forms["value/negative" if value < 0 else "value/nonnegative"] += 1


def required_forms(*_):
    spec, _ = specifications()
    return {*(f"fixture/{choice}" for choice in spec["finite"]["fixture"]),
            *(f"operation/{choice}" for choice in spec["finite"]["operation"]), "value/negative", "value/nonnegative"}


def controls():
    item = case("mysql-8.4.7", "shadowed", "query", -1)
    native = prediction(item)
    check_observation(item, native, "correct temporary binding")
    altered = {**native, "result": prediction(case("mysql-8.4.7", "persistent", "query", -1))["result"]}
    try:
        check_observation(item, altered, "wrong permanent values")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"wrong-binding-values": {"input": item, **error.detail}}
    raise Failure("mysql-session-comparator-missed-wrong-namespace")


def boundary(item, response):
    if rejects(response, item["boundary"]):
        return
    require(response["status"] == "constructed", "mysql-session-mutant-response", response)
    raise Failure("mysql-session-invalid-published", {"sql": response["sql"], "observed": response, "expected": item["boundary"]})


def fault_probe(item, response, db):
    if "boundary" in item:
        boundary(item, response)
    else:
        probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            boundary(rule.item, record["healthyResponse"])
            boundary(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(prediction(rule.item), witness["expected"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("mysql-session-fault-survived", rule.name)


def faults(_, tag):
    root = "sql-semantics-mysql/src"
    source = f"{root}/Ir/SourceBinding.php"
    query = case(tag, "shadowed", "query", -1)
    predicate = case(tag, "shadowed", "delete", 2)
    analyzed_query = analyzed_request(query, query_sql(TABLE, -1))
    view, exception, message = list(negatives(tag))[3]
    return [
        RenderFault("lose-temporary-precedence", f"{root}/Model/Construction/Derivation.php",
                    "->find($this->context, $name, $namespace)", "->find($this->context, $name, TableNamespace::PersistentPreferred)",
                    query, STRUCTURE, analyzed_query),
        RenderFault("lose-temporary-insert-destination", source,
                    "new TableSource($target->schema, $target->table, $columns, $types, $target->kind)",
                    "new TableSource($target->schema, $target->table, $columns, $types)",
                    case(tag, "shadowed", "insert-values", 5), STRUCTURE,
                    analyzed_request(case(tag, "shadowed", "insert-values", 5), f"INSERT INTO {TABLE} (n) VALUES (5)")),
        RenderFault("misclassify-temporary-as-view", source,
                    "? ExternalRelationKind::TemporaryTable : ExternalRelationKind::BaseTable",
                    "? ExternalRelationKind::View : ExternalRelationKind::BaseTable", query, STRUCTURE, analyzed_query),
        RenderFault("redirect-temporary-write", f"{root}/Ir/Mutation/Delete.php",
                    "$this->target->schema->quoted()", repr("`" + DECOY + "`"), predicate, "meaning-mismatch", direct(predicate)),
        RenderFault("erase-temporary-predicate", f"{root}/Ir/Mutation/Delete.php",
                    "if ($this->where === null)", "if (true)", predicate, "meaning-mismatch", direct(predicate)),
        RenderFault("permit-persistent-view-over-session-table", f"{root}/Ir/Schema/View/ViewQueryBounds.php",
                    "$source->kind === ExternalRelationKind::BaseTable", "$source->kind->isTable()",
                    {"boundary": {"exception": exception, "message": message}}, "mysql-session-invalid-published", view),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
