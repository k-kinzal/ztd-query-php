"""Compare resolved MySQL base and external view reads with a relational model."""

from collections import Counter
from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, rejects, same
from run_server_join import OBSERVER, main, probe
from server_join_cases import case, compare, is_join, join, leaves, rows, slot, truth, value
from sqlite_domain import RenderFault

LABEL = "mysql-external-views"
CONTRACT_NAME = "mysql-external-views-v1.json"
CONTRACT = "24ca8bdc80df5f8358b4020de5fad7219da5a2c58d045f4ecb0ff45cd4a8b18d"
COMPLETE_SEEDS = True
AXES = ("source", "placement", "projection", "filter")
BASE, ANCHOR = "ir_fixture.ir_v_base", "ir_fixture.ir_v_anchor"
MERGE, TEMPORARY = "ir_fixture.ir_v_merge", "ir_fixture.ir_v_temporary"
DECOY, NESTED = "ir_v_decoy.ir_v_merge", "ir_fixture.ir_v_nested"
IDENTITIES = {"base": BASE, "merge-view": MERGE, "temporary-view": TEMPORARY}
VIEWS = (MERGE, TEMPORARY, DECOY, NESTED)
HEADING, RENAMED = ("id", "label"), ("key_value", "label_value")
COLLATION = "utf8mb4_bin"
TEXT = ["varchar", 8, COLLATION]
TYPES = {name: native for heading in (HEADING, RENAMED) for name, native in zip(heading, ("int", TEXT))}
TYPES_SQL = f"id INT, label VARCHAR(8) CHARACTER SET utf8mb4 COLLATE {COLLATION}"
DECLARATIONS = [
    f"CREATE TABLE {BASE} ({TYPES_SQL})",
    f"CREATE TABLE {ANCHOR} ({TYPES_SQL})",
    f"CREATE ALGORITHM=MERGE VIEW {MERGE} (id, label) AS SELECT id, label FROM {BASE} WHERE id > 1",
    f"CREATE ALGORITHM=TEMPTABLE VIEW {TEMPORARY} AS SELECT id, label FROM {BASE} WHERE id <> 2 OR id IS NULL",
    f"CREATE VIEW {DECOY} AS SELECT id, label FROM {BASE} WHERE id = 1",
    f"CREATE VIEW {NESTED} (key_value, label_value) AS SELECT id, label FROM {MERGE}",
]
BASE_ROWS = [(1, "seed"), (2, "β"), (2, "β"), (3, None), (None, "orphan")]
ANCHOR_ROWS = [(1, "a1"), (2, "a2"), (4, "a4"), (None, "an")]
DIAGNOSTICS = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\"
MISSING_COLLATION = {"exception": DIAGNOSTICS + "InvalidConstruction",
                     "message": "The text slot requires an explicit utf8mb4_bin collation."}


def specifications():
    path, profile = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "mysql-external-view-contract-mismatch")
    return json.loads(path.read_text()), {"mysql": json.loads(profile.read_text())["profiles"]["mysql"]}


def prepare(_, db):
    def atom(value):
        if value is None:
            return "NULL"
        if isinstance(value, int):
            return str(value)
        return f"CONVERT(0x{value.encode().hex()} USING utf8mb4)"

    inserts = []
    for name, data in ((BASE, BASE_ROWS), (ANCHOR, ANCHOR_ROWS)):
        cells = ", ".join("(" + ", ".join(map(atom, row)) + ")" for row in data)
        inserts.append(f"INSERT INTO {name} VALUES {cells}")
    setup = db.request({"fixture": [
        "CREATE DATABASE IF NOT EXISTS ir_v_decoy",
        *(f"DROP VIEW IF EXISTS {name}" for name in reversed(VIEWS)),
        *(f"DROP TABLE IF EXISTS {name}" for name in (BASE, ANCHOR)),
        *DECLARATIONS[:2], *inserts, *DECLARATIONS[2:],
        f"UPDATE {BASE} SET label = 'changed' WHERE id IN (1, 2)",
    ]})
    metadata = db.request({"sql": "SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, "
                                  "CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS "
                                  "WHERE TABLE_SCHEMA IN ('ir_fixture', 'ir_v_decoy') "
                                  "AND TABLE_NAME IN ('ir_v_base','ir_v_anchor','ir_v_merge','ir_v_temporary','ir_v_nested') "
                                  "ORDER BY BINARY TABLE_SCHEMA, BINARY TABLE_NAME, ORDINAL_POSITION"})
    return {"fixture": setup, "catalog": metadata}


def check_preparation(_, _environment, prepared):
    same({"status": "fixture"}, prepared["fixture"])
    expected = []
    for identity in sorted((BASE, ANCHOR, *VIEWS)):
        schema, name = identity.split(".")
        heading = RENAMED if identity == NESTED else HEADING
        expected.extend([[schema, name, heading[0], "int", None, None, None],
                         [schema, name, heading[1], "varchar", 8, "utf8mb4", COLLATION]])
    require(prepared["catalog"]["status"] == "rows", "mysql-external-view-catalog", prepared)
    same(expected, prepared["catalog"]["rows"])


def source(identity, names=HEADING):
    schema, name = identity.split(".")
    return {"schema": schema, "table": name, "columns": list(names),
            **({"columnTypes": [TYPES[name] for name in names]} if any(TYPES[name] == TEXT for name in names) else {}),
            **({"relationKind": "view"} if identity in VIEWS else {})}


def read(name, names, occurrence=0):
    position = names.index(name)
    return ["text-slot", position, occurrence] if TYPES[name] == TEXT else slot(position, occurrence)


def finite(tag, kind, placement, projection, predicate):
    origin = source(IDENTITIES[kind])
    occurrence = int(placement == "left-join")
    relation = join("LEFT", source(ANCHOR), origin, compare("=", slot(0), slot(0, 1))) if occurrence else origin
    names = HEADING if projection == "both" else HEADING[:1] if projection == "signed" else HEADING[1:]
    where = ["null-test", True, read("label", HEADING, occurrence)] if predicate == "non-null" else None
    return case("mysql", tag, relation, [[read(name, HEADING, occurrence), name] for name in names], where)


def finite_ids(_, spec, _tag):
    finite_ = spec["finite"]
    grid = set(product(*(finite_[axis] for axis in AXES)))
    require(len(grid) == finite_["identities"], "mysql-external-view-finite-size")
    return grid


def relation_id(relation):
    return relation["schema"] + "." + relation["table"]


def finite_id(_, actual, _item):
    relation = actual["source"]
    joined = is_join(relation)
    origin = relation["right"] if joined else relation
    role = {("slot",): "signed", ("text-slot",): "text", ("slot", "text-slot"): "both"}
    return ({identity: key for key, identity in IDENTITIES.items()}[relation_id(origin)],
            "left-join" if joined else "scan", role[tuple(term[0] for term, _ in actual["columns"])],
            "absent" if actual["where"] is None else "non-null")


def analyzed(tag):
    yield f"SELECT v.id AS mapped FROM {MERGE} AS v", case("mysql", tag, source(MERGE, ("id",)), [[slot(0), "mapped"]])
    yield f"SELECT id AS plain,v.id AS qualified FROM {MERGE} AS v", case(
        "mysql", tag, source(MERGE, ("id",)), [[slot(0), "plain"], [slot(0), "qualified"]])
    yield f"SELECT label FROM {MERGE}", case(
        "mysql", tag, source(MERGE, ("label",)), [[read("label", ("label",)), "label"]])
    yield f"SELECT label FROM {TEMPORARY}", case(
        "mysql", tag, source(TEMPORARY, ("label",)), [[read("label", ("label",)), "label"]])
    yield f"SELECT * FROM {DECOY}", case("mysql", tag, source(DECOY), [[read(name, HEADING), name] for name in HEADING])
    yield f"SELECT v.id,v.label FROM {ANCHOR} a LEFT JOIN {MERGE} v ON a.id=v.id", finite(
        tag, "merge-view", "left-join", "both", "absent")
    yield f"SELECT * FROM {MERGE}", finite(tag, "merge-view", "scan", "both", "absent")
    yield f"SELECT key_value,label_value FROM {NESTED}", case(
        "mysql", tag, source(NESTED, RENAMED), [[read(name, RENAMED), name] for name in RENAMED])


def request(tag, sql, declarations=DECLARATIONS):
    return {"dialect": "mysql", "release": tag, "sql": sql, "declarations": declarations}


def negatives(tag):
    target = {"dialect": "mysql", "release": tag, "target": source(MERGE, ("id",)), "where": None}
    for article, statement, extra in (
        ("An", "INSERT", {"rows": [["8"]]}),
        ("An", "INSERT", {"insertQuery": case("mysql", tag, None, [[["integer", "8"], "id"]])}),
        ("An", "UPDATE", {"assignments": [[slot(0), ["integer", "8"]]]}),
        ("A", "DELETE", {}),
    ):
        yield {**target, **extra}, {"exception": DIAGNOSTICS + "InvalidConstruction",
                                   "message": f"{article} {statement} target must be a base table."}
    yield request(tag, f"UPDATE {MERGE} SET id = 4"), {
        "exception": DIAGNOSTICS + "InvalidConstruction", "message": "A write requires a resolved base table."}
    yield request(tag, f"SELECT unavailable FROM {MERGE}"), {
        "exception": DIAGNOSTICS + "SemanticException", "message": "unavailable"}
    broken = [line.replace(f" COLLATE {COLLATION}", "") if line == DECLARATIONS[0] else line for line in DECLARATIONS]
    yield request(tag, f"SELECT label FROM {MERGE}", broken), MISSING_COLLATION


def seeded(tag, seed, amount):
    rng = Random(seed)
    choices = (BASE, ANCHOR, *VIEWS)
    for index in range(amount):
        identities = [rng.choice(choices) for _ in range(1 + index % 3)]
        headings = [RENAMED if name == NESTED else HEADING for name in identities]
        relation = source(identities[0], headings[0])
        for occurrence, identity in enumerate(identities[1:], 1):
            role = rng.choice(("LEFT", "CROSS", "INNER"))
            on = None if role == "CROSS" else compare("=", slot(0), slot(0, occurrence))
            relation = join(role, relation, source(identity, headings[occurrence]), on)
        references = [read(name, names, occurrence) for occurrence, names in enumerate(headings) for name in names]
        result = [[rng.choice(references), rng.choice(("field", "id", "値 ` key"))] for _ in range(1 + rng.randrange(5))]
        if index % 5 == 0:
            result.append(result[0])
        where = rng.choice((None, ["null-test", bool(rng.getrandbits(1)), rng.choice(references)]))
        yield case("mysql", tag, relation, result, where)


def requests(_, tag, spec):
    for index, dimensions in enumerate(product(*(spec["finite"][axis] for axis in AXES))):
        item = finite(tag, *dimensions)
        yield f"finite/{index}", item, item, None
    inputs = list(analyzed(tag))
    require(len(inputs) == len(spec["analysis"]), "mysql-external-view-analysis-count")
    for index, (sql, item) in enumerate(inputs):
        yield f"analyze/{index}", request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, reason


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
    require(role == ("text-slot" if TYPES[name] == TEXT else "slot"), "mysql-external-view-reference-role", term)
    return role, occurrence, name


def graph(item):
    def node(relation):
        if is_join(relation):
            return relation["kind"], node(relation["left"]), node(relation["right"]), address(relation["on"], leaves(relation))
        kinds = [TYPES[name] for name in relation["columns"]]
        require(relation.get("columnTypes", ["int"] * len(kinds)) == kinds, "mysql-external-view-heading", relation)
        return relation_id(relation), relation.get("relationKind", "table")

    scope = leaves(item["source"])
    return (node(item["source"]), [(address(term, scope), label) for term, label in item["columns"]],
            address(item["where"], scope), item.get("distinct", False), item.get("orderBy", []), item.get("limit"))


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "mysql-external-view-rejected", reply)
    require(graph(reply) == graph(item), "mysql-external-view-graph",
            {"expected": item, "observed": reply, "sql": reply["sql"]})


def external(relation):
    identity = relation_id(relation)
    current = [(key, "changed" if key in (1, 2) else text) for key, text in BASE_ROWS]
    if identity == BASE:
        return HEADING, current
    if identity == ANCHOR:
        return HEADING, ANCHOR_ROWS
    if identity in (MERGE, NESTED):
        return (RENAMED if identity == NESTED else HEADING), [row for row in current if row[0] is not None and row[0] > 1]
    if identity == TEMPORARY:
        return HEADING, [row for row in current if row[0] != 2]
    require(identity == DECOY, "mysql-external-view-unknown-fixture", identity)
    return HEADING, [row for row in current if row[0] == 1]


def prediction(item):
    fields = [[label, "VAR_STRING" if term[0] == "text-slot" else "LONG"] for term, label in item["columns"]]
    projected = [[value(term, row, "mysql") for term, _ in item["columns"]]
                 for row in rows(item["source"], "mysql", table_rows=external) if truth(item["where"], row, "mysql")]
    return {"status": "rows", "fields": fields, "rows": projected}


def observer_request(_, sql):
    return {"sql": sql}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    expected = prediction(item)
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual["status"] == "rows", "mysql-external-view-server-rejection", witness)
    require(actual["fields"] == expected["fields"]
            and Counter(map(tuple, actual["rows"])) == Counter(map(tuple, expected["rows"])), "meaning-mismatch", witness)


def sample_forms(forms, reply, *_):
    scope = leaves(reply["source"])
    forms["placement/" + ("scan" if len(scope) == 1 else "nested" if len(scope) == 3 else "join")] += 1
    forms["filter/" + (reply["where"][0] if reply["where"] else "absent")] += 1
    if len(reply["columns"]) != len({json.dumps(term) for term, _ in reply["columns"]}):
        forms["repeated-projection"] += 1
    for relation in scope:
        forms["identity/" + relation_id(relation)] += 1
    for term, _ in reply["columns"]:
        forms["role/" + term[0]] += 1
    if any(None in row for row in prediction(reply | {"dialect": "mysql"})["rows"]):
        forms["predicted-null"] += 1


def required_forms(*_):
    return {"placement/scan", "placement/join", "placement/nested", "filter/null-test", "filter/absent",
            "repeated-projection", "role/slot", "role/text-slot", "predicted-null",
            *("identity/" + identity for identity in (BASE, ANCHOR, *VIEWS))}


def controls():
    item = finite("mysql-9.1.0", "merge-view", "left-join", "both", "absent")
    healthy = prediction(item)
    check_observation(item, healthy, "external view control")
    variants = {
        "erase-duplicate-row": {**healthy, "rows": [list(row) for row in dict.fromkeys(map(tuple, healthy["rows"]))]},
        "erase-null-extension": {**healthy, "rows": [row for row in healthy["rows"] if row[0] is not None]},
        "wrong-varchar-native-type": {**healthy, "fields": [["id", "LONG"], ["label", "STRING"]]},
    }
    reports = {}
    for name, variant in variants.items():
        try:
            check_observation(item, variant, name)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            reports[name] = {"input": item, **error.detail}
        else:
            raise Failure("mysql-external-view-comparator-accepted", name)
    return reports


def boundary(item, reply):
    if rejects(reply, item["boundary"]):
        return
    require(reply["status"] == "constructed", "mysql-external-view-mutant-response", reply)
    raise Failure("mysql-external-view-published-write", {"expected": item["boundary"], "observed": reply, "sql": reply["sql"]})


def accepted_collation(item, reply):
    if rejects(reply, MISSING_COLLATION):
        raise Failure("mysql-external-view-collation-lost", {"sql": item["raw"], "observed": reply, "expected": item["select"]})
    check_value(item["select"], reply)


def fault_probe(item, reply, db):
    if "boundary" in item:
        boundary(item, reply)
    elif "raw" in item:
        accepted_collation(item, reply)
        probe(item["select"], reply, db, sys.modules[__name__])
    else:
        probe(item, reply, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    try:
        if "boundary" in rule.item:
            boundary(rule.item, record["healthyResponse"])
            boundary(rule.item, record["mutantResponse"])
        elif "raw" in rule.item:
            accepted_collation(rule.item, record["healthyResponse"])
            accepted_collation(rule.item, record["mutantResponse"])
        else:
            check_value(rule.item, record["healthyResponse"])
            check_value(rule.item, record["mutantResponse"])
            same(prediction(rule.item), witness["expected"])
            check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("mysql-external-view-undetected-fault", rule.name)


def faults(_, tag):
    ir = "sql-semantics-mysql/src/Ir/"
    renderer = ir + "SelectSql.php"
    item = finite(tag, "merge-view", "scan", "both", "absent")
    input_sql = f"SELECT * FROM {MERGE}"
    invalid, reason = list(negatives(tag))[2]
    return [
        RenderFault("erase-mysql-view-kind", ir + "SourceBinding.php",
                    "RelationKind::View => ExternalRelationKind::View", "RelationKind::View => ExternalRelationKind::BaseTable",
                    item, "mysql-external-view-graph", request(tag, input_sql)),
        RenderFault("select-mysql-decoy-view", renderer, "$source->schema->quoted()", "'`ir_v_decoy`'", item, "meaning-mismatch"),
        RenderFault("select-mysql-unfiltered-base", renderer, "$source->table->quoted()", "'`ir_v_base`'", item, "meaning-mismatch"),
        RenderFault("lose-mysql-view-field-collation", "sql-semantics-mysql/src/Rules/TableDefinition/TableDeclaration.php",
                    "$field->column()?->collation", "null",
                    {"raw": input_sql, "select": item}, "mysql-external-view-collation-lost", request(tag, input_sql)),
        RenderFault("admit-mysql-view-in-update", ir + "Mutation/Update.php", "$target->kind->isTable()", "true",
                    {"boundary": reason}, "mysql-external-view-published-write", invalid),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
