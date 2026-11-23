"""Compare resolved PostgreSQL table, view and materialized relation reads."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, rejects, same
from run_server_join import OBSERVER, main, probe
from server_join_cases import compare, is_join, join, leaves, rows, slot, truth, value
from server_result_cases import case, match, result_window
from sqlite_domain import RenderFault

LABEL = "postgres-external-source-kinds"
CONTRACT_NAME = "postgres-external-source-kinds-v1.json"
CONTRACT = "c37f2978ffaf44e07bae85e35df9f34cc32a853e1cdabfe5d0e7005a4f1c73c9"
COMPLETE_SEEDS = True
AXES = ("kinds", "policies", "placements", "projections", "filters", "order")
BASE, CHILD = "public.ir_ext_base", "public.ir_ext_child"
LIVE, MATERIALIZED = "public.ir_ext_live", "public.ir_ext_snapshot"
SHADOW, ANCHOR = "ir_ext_decoy.ir_ext_live", "public.ir_ext_anchor"
FOREIGN = "public.ir_ext_foreign"
KIND = {BASE: "table", LIVE: "view", MATERIALIZED: "materialized-view", SHADOW: "view", ANCHOR: "table"}
IDENTITY = {KIND[name]: name for name in (BASE, LIVE, MATERIALIZED)}
HEADING = ("id", "wide", "flag", "payload")
TYPES = (("slot", "int4", 23), ("wide-slot", "int8", 20), ("bool-slot", "bool", 16), ("text-slot", "text", 25))
TYPED_FIELD = {name: kind for name, kind in zip(HEADING, TYPES)}
BASE_DDL = f"CREATE TABLE {BASE} (id int, wide bigint, flag bool, payload text)"
ANCHOR_DDL = f"CREATE TABLE {ANCHOR} (id int, wide bigint, flag bool, payload text)"
LIVE_DDL = f"CREATE VIEW {LIVE} AS SELECT * FROM {BASE} WHERE flag IS DISTINCT FROM FALSE"
MATERIALIZED_DDL = f"CREATE MATERIALIZED VIEW {MATERIALIZED} AS SELECT * FROM {BASE}"
SHADOW_DDL = f"CREATE VIEW {SHADOW} AS SELECT 90 AS id, 8::bigint AS wide, FALSE AS flag, 'decoy'::text AS payload"
DECLARATIONS = [BASE_DDL, ANCHOR_DDL, LIVE_DDL, MATERIALIZED_DDL, SHADOW_DDL]
BASE_INPUTS = [(1, 2147483648, True, "base"), (2, None, False, "repeat"), (2, None, False, "repeat"),
               (None, -2147483649, None, None)]
CHILD_INPUTS = [(3, 9223372036854775807, True, "child")]
ANCHOR_INPUTS = [(1, 111, True, "a"), (2, 222, False, "b"), (3, 333, None, "c"), (4, None, True, None), (None, None, None, None)]
DIAGNOSTIC = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\InvalidConstruction"
SEMANTIC = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\SemanticException"


def specifications():
    path, profile = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-external-source-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def prepare(_, db):
    return db.request({"fixture": [
        f"DROP SCHEMA IF EXISTS {SHADOW.split('.')[0]} CASCADE", f"CREATE SCHEMA {SHADOW.split('.')[0]}",
        f"DROP VIEW IF EXISTS {LIVE}", f"DROP MATERIALIZED VIEW IF EXISTS {MATERIALIZED}",
        f"DROP TABLE IF EXISTS {CHILD}", f"DROP TABLE IF EXISTS {BASE}", f"DROP TABLE IF EXISTS {ANCHOR}",
        BASE_DDL, ANCHOR_DDL,
        f"INSERT INTO {BASE} VALUES (1,2147483648,true,'base'),(2,NULL,false,'repeat'),(2,NULL,false,'repeat'),(NULL,-2147483649,NULL,NULL)",
        f"INSERT INTO {ANCHOR} VALUES (1,111,true,'a'),(2,222,false,'b'),(3,333,NULL,'c'),(4,NULL,true,NULL),(NULL,NULL,NULL,NULL)",
        f"CREATE TABLE {CHILD} () INHERITS ({BASE})",
        f"INSERT INTO {CHILD} VALUES (3,9223372036854775807,true,'child')",
        LIVE_DDL, MATERIALIZED_DDL, SHADOW_DDL,
        f"UPDATE ONLY {BASE} SET payload = 'changed' WHERE id = 1",
    ]})


def check_preparation(_, environment, response):
    same({"status": "fixture"}, response)
    require(environment["database"][-1] == "pg_catalog, public", "postgres-external-source-profile")


def source(identity, only=False):
    schema, name = identity.split(".")
    kind = KIND[identity]
    return {"schema": schema, "table": name, "columns": list(HEADING), "only": only,
            "columnKinds": [field[1] for field in TYPES],
            **({"relationKind": kind} if kind != "table" else {})}


def read(name, occurrence=0):
    return [TYPED_FIELD[name][0], HEADING.index(name), *([occurrence] if occurrence else [])]


def query(tag, relation, fields, where=None, order=()):
    return case("postgres", tag, relation, fields, False, list(order), None, where)


def finite(tag, kind, policy, placement, projection, filter_mode, order):
    origin = source(IDENTITY[kind], policy == "only")
    occurrence = int(placement == "left-join")
    relation = join("LEFT", source(ANCHOR), origin, compare("=", slot(0), slot(0, 1))) if occurrence else origin
    names = HEADING if projection == "all-fields" else ("wide", "payload")
    fields = [[read(name, occurrence), name] for name in names]
    where = read("flag", occurrence) if filter_mode == "flag" else None
    return query(tag, relation, fields, where, [[1, False, True]] if order == "ordered" else [])


def finite_ids(_, spec, _tag):
    finite_ = spec["finite"]
    grid = set(product(*(finite_[axis] for axis in AXES)))
    require(len(grid) == finite_["identities"], "postgres-external-source-finite-size")
    return grid


def finite_id(_, actual, _item):
    relation = actual["source"]
    joined = is_join(relation)
    inspected = relation["right"] if joined else relation
    return (inspected.get("relationKind", "table"), "only" if inspected["only"] else "all",
            "left-join" if joined else "scan", "all-fields" if len(actual["columns"]) == 4 else "wide-and-text",
            "absent" if actual["where"] is None else "flag", "ordered" if actual["orderBy"] else "unordered")


def analyzed(tag):
    yield f"SELECT * FROM {LIVE}", finite(tag, "view", "all", "scan", "all-fields", "absent", "unordered")
    yield f"SELECT wide AS first, v.wide AS same FROM {LIVE} v", query(
        tag, source(LIVE), [[read("wide"), "first"], [read("wide"), "same"]])
    yield f"SELECT * FROM ONLY {LIVE}", finite(tag, "view", "only", "scan", "all-fields", "absent", "unordered")
    yield f"SELECT wide,payload FROM ONLY ({MATERIALIZED}) ORDER BY 1 NULLS FIRST", finite(
        tag, "materialized-view", "only", "scan", "wide-and-text", "absent", "ordered")
    yield f"SELECT wide,payload FROM {BASE}* WHERE flag", finite(tag, "table", "all", "scan", "wide-and-text", "flag", "unordered")
    yield f"SELECT * FROM {SHADOW}", query(tag, source(SHADOW), [[read(name), name] for name in HEADING])
    yield f"SELECT r.wide,r.payload FROM {ANCHOR} a LEFT JOIN {MATERIALIZED} r ON a.id=r.id", finite(
        tag, "materialized-view", "all", "left-join", "wide-and-text", "absent", "unordered")
    yield f"SELECT v.payload AS live,d.payload AS decoy FROM {LIVE} v CROSS JOIN {SHADOW} d", query(
        tag, join("CROSS", source(LIVE), source(SHADOW)), [[read("payload"), "live"], [read("payload", 1), "decoy"]])


def request(tag, sql, declarations=DECLARATIONS):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": declarations}


def negatives(tag):
    messages = {"INSERT": "An INSERT", "UPDATE": "An UPDATE", "DELETE": "A DELETE"}
    for mode in ("INSERT", "UPDATE", "DELETE"):
        for identity in (LIVE, MATERIALIZED):
            item = {"dialect": "postgres", "release": tag, "target": source(identity), "where": None}
            if mode == "INSERT":
                item.update(written=[slot(0)], rows=[["8"]])
            if mode == "UPDATE":
                item["assignments"] = [[slot(0), ["integer", "8"]]]
            yield item, {"exception": DIAGNOSTIC, "message": f"{messages[mode]} target must be a base table."}
    yield request(tag, f"SELECT unavailable FROM {LIVE}"), {"exception": SEMANTIC, "message": "unavailable"}
    yield request(tag, f"SELECT id FROM {FOREIGN}", [f"CREATE FOREIGN TABLE {FOREIGN} (id int) SERVER foreign_fixture"]), {
        "exception": DIAGNOSTIC, "message": "The source requires an admitted external relation kind."}
    yield request(tag, f"UPDATE {LIVE} SET id = 4"), {"exception": DIAGNOSTIC, "message": "A write requires a resolved base table."}


def seeded(tag, seed, amount):
    rng = random.Random(seed)
    for index in range(amount):
        width = 1 + index % 3
        origins = [source(rng.choice(tuple(KIND)), bool(rng.getrandbits(1))) for _ in range(width)]
        relation = origins[0]
        for occurrence, origin in enumerate(origins[1:], 1):
            join_kind = rng.choice(("INNER", "LEFT", "CROSS"))
            on = None if join_kind == "CROSS" else compare("=", slot(0), slot(0, occurrence))
            relation = join(join_kind, relation, origin, on)
        choices = [(name, occurrence) for name in HEADING for occurrence in range(width)]
        fields = [[read(*rng.choice(choices)), rng.choice(("field", "id", '値 " key'))] for _ in range(1 + rng.randrange(6))]
        if index % 5 == 0:
            fields.append(fields[0])
        where = rng.choice((None, read("flag", rng.randrange(width)),
                            ["null-test", bool(rng.getrandbits(1)), read(*rng.choice(choices))]))
        numeric = [position + 1 for position, (term, _) in enumerate(fields) if term[0] in ("slot", "wide-slot")]
        order = [[position, bool(rng.getrandbits(1)), bool(rng.getrandbits(1))] for position in numeric] if index % 2 else []
        yield query(tag, relation, fields, where, order)


def requests(_, tag, spec):
    finite_ = spec["finite"]
    for index, dimensions in enumerate(product(*(finite_[axis] for axis in AXES))):
        item = finite(tag, *dimensions)
        yield f"finite/{index}", item, item, None
    inputs = list(analyzed(tag))
    require(len(inputs) == len(spec["analysis"]), "postgres-external-source-analysis-count")
    for index, (sql, item) in enumerate(inputs):
        yield f"analyze/{index}", request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, reason


def identity(relation):
    return relation["schema"] + "." + relation["table"]


def address(term, scope):
    if term is None:
        return None
    role, ordinal, *rest = term
    if role == "comparison":
        return role, ordinal, *(address(child, scope) for child in rest)
    if role == "null-test":
        return role, ordinal, address(rest[0], scope)
    occurrence = rest[0] if rest else 0
    name = scope[occurrence]["columns"][ordinal]
    require(TYPED_FIELD[name][0] == role, "postgres-external-source-reference-kind", term)
    return role, occurrence, name


def graph(item):
    def node(relation):
        if is_join(relation):
            return relation["kind"], node(relation["left"]), node(relation["right"]), address(relation["on"], leaves(relation))
        layout = [TYPED_FIELD[name][1] for name in relation["columns"]]
        require(layout == relation.get("columnKinds", ["int4"] * len(layout)), "postgres-external-source-heading", relation)
        return identity(relation), relation.get("relationKind", "table"), relation["only"]

    scope = leaves(item["source"])
    return (node(item["source"]), tuple((address(term, scope), label) for term, label in item["columns"]),
            address(item["where"], scope), item["orderBy"], item["distinct"], item["limit"])


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-external-source-rejected", reply)
    require(graph(reply) == graph(item), "postgres-external-source-graph",
            {"expected": item, "observed": reply, "sql": reply["sql"]})


def external(relation):
    name = identity(relation)
    live = [(key, wide, flag, "changed" if key == 1 else payload) for key, wide, flag, payload in BASE_INPUTS]
    if name == BASE:
        return HEADING, live + ([] if relation["only"] else CHILD_INPUTS)
    if name == LIVE:
        return HEADING, [row for row in live + CHILD_INPUTS if row[2] is not False]
    if name == MATERIALIZED:
        return HEADING, BASE_INPUTS + CHILD_INPUTS
    if name == SHADOW:
        return HEADING, [(90, 8, False, "decoy")]
    require(name == ANCHOR, "postgres-external-source-unknown-fixture", name)
    return HEADING, ANCHOR_INPUTS


def prediction(item):
    types = {role: [name, oid] for role, name, oid in TYPES}
    fields = [[label, *types[term[0]]] for term, label in item["columns"]]
    observed = [[value(term, row, "postgres") for term, _ in item["columns"]]
                for row in rows(item["source"], "postgres", table_rows=external)
                if truth(item["where"], row, "postgres")]
    return result_window(fields, observed, item["orderBy"], None)


def observer_request(_, sql):
    return {"sql": sql, "observePostgresTypeIds": True}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    match(prediction(item), actual, sql)


def sample_forms(forms, reply, *_):
    scope = leaves(reply["source"])
    forms["placement/" + ("scan" if len(scope) == 1 else "nested" if len(scope) == 3 else "join")] += 1
    forms["order/" + ("explicit" if reply["orderBy"] else "absent")] += 1
    forms["filter/" + (reply["where"][0] if reply["where"] else "absent")] += 1
    if len(reply["columns"]) != len({json.dumps(term) for term, _ in reply["columns"]}):
        forms["repeated-projection"] += 1
    for relation in scope:
        forms["kind/" + relation.get("relationKind", "table")] += 1
        forms["policy/" + ("ONLY" if relation["only"] else "all")] += 1
    for term, _ in reply["columns"]:
        forms["role/" + term[0]] += 1


def required_forms(*_):
    return {"placement/scan", "placement/join", "placement/nested", "order/explicit", "order/absent",
            "filter/bool-slot", "filter/null-test", "filter/absent", "repeated-projection",
            "kind/table", "kind/view", "kind/materialized-view", "policy/ONLY", "policy/all",
            *("role/" + role for role, _, _ in TYPES)}


def mismatch_controls(item, healthy, cases):
    check_observation(item, healthy, "external source control")
    evidence = {}
    for name, actual in cases.items():
        try:
            check_observation(item, actual, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-external-source-control-signal", name)
            evidence[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-external-source-control-accepted", name)
    return evidence


def controls():
    item = finite("pg-17.2", "materialized-view", "all", "scan", "all-fields", "absent", "ordered")
    outcome = prediction(item)
    healthy = {"status": "rows", "fields": outcome["fields"], "rows": [row for band in outcome["bands"] for row in band["members"]]}
    return mismatch_controls(item, healthy, {
        "stale-versus-live": {**healthy, "rows": [[*row[:-1], "changed"] if row[0] == 1 else row for row in healthy["rows"]]},
        "wrong-native-width": {**healthy, "fields": [healthy["fields"][0], ["wide", "int4", 23], *healthy["fields"][2:]]},
        "erase-duplicate-row": {**healthy, "rows": [list(row) for row in dict.fromkeys(map(tuple, healthy["rows"]))]},
    })


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "postgres-external-source-mutant-response", reply)
    raise Failure("postgres-external-source-published-write", {"expected": item["boundary"], "observed": reply, "sql": reply["sql"]})


def fault_probe(item, reply, db):
    if "boundary" in item:
        boundary(item, reply)
    else:
        probe(item, reply, db, sys.modules[__name__])


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
    raise Failure("postgres-external-source-undetected-fault", rule.name)


def faults(_, tag):
    ir = "sql-semantics-postgres/src/Ir/"
    renderer = ir + "SelectSql.php"
    live = finite(tag, "view", "all", "scan", "all-fields", "absent", "unordered")
    snapshot = finite(tag, "materialized-view", "all", "scan", "all-fields", "absent", "ordered")
    only = finite(tag, "table", "only", "scan", "all-fields", "absent", "unordered")
    invalid, reason = list(negatives(tag))[2]
    return [
        RenderFault("forget-postgres-view-kind", ir + "SourceBinding.php",
                    "RelationKind::View => ExternalRelationKind::View", "RelationKind::View => ExternalRelationKind::BaseTable",
                    live, "postgres-external-source-graph", request(tag, f"SELECT * FROM {LIVE}")),
        RenderFault("switch-to-decoy-schema", renderer, "$source->schema->quoted()", "'\"ir_ext_decoy\"'", live, "meaning-mismatch"),
        RenderFault("switch-snapshot-to-view", renderer, "$source->table->quoted()", "'\"ir_ext_live\"'", snapshot, "meaning-mismatch"),
        RenderFault("ignore-only-on-inherited-source", renderer, "($source->only ? 'ONLY ' : '')", "''", only, "meaning-mismatch"),
        RenderFault("admit-view-in-update", ir + "Mutation/Update.php", "$target->kind === ExternalRelationKind::BaseTable", "true",
                    {"boundary": reason}, "postgres-external-source-published-write", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
