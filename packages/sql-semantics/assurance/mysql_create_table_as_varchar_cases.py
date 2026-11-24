"""Direct MySQL CTAS cases for resolved native VARCHAR fields and row width."""

from itertools import product
from random import Random

import mysql_create_table_as_source_cases as resolved
import run_server_mysql_create_table_as as constants
from run import require
from run_server_mysql_session_create_table import COLLATION, TEMP
from server_join_cases import slot

AXES = ("schema", "topology", "destinationKind", "layout", "distinct", "predicate")
FIELDS = ("n", "m", "v", "z", "w")
WIDTH_ERROR = "The projected logical table record cannot exceed 65535 bytes."
SLOT_ERROR = "A VARCHAR slot must belong to a visible physical input occurrence."


def text(position):
    return ["text-slot", position, 0]


def varchar(width, nullable=True):
    return ["varchar", width, COLLATION, *([] if nullable else [False])]


def columns(layout):
    return {
        "text": [[text(2), "label"], [text(2), "repeat_value"], [text(3), "empty_value"]],
        "mixed": [[slot(0), "number"], [text(4), "tail"], [text(2), "label"]],
    }[layout]


def case(tag, schema, topology, kind, projected, where=None, distinct=False, order=(), limit=None,
         fields=FIELDS, destination="primary", collision="absent", guard=False, variant=0, layout="text"):
    item = resolved.construction(tag, schema, topology, kind, projected, where, distinct,
                                 order, limit, destination, collision, guard, fields=fields, variant=variant)
    source = item["mysqlCreateTableAs"]["query"]["source"]
    base = (10 if schema == "primary" else 50) + variant + (0 if topology == "persistent" else 81)
    if layout == "width-edge":
        types = {"v": varchar(8191), "z": varchar(0)}
    else:
        types = {"n": "int", "m": "int", "v": varchar(5 if layout == "retained-text" else 8), "z": varchar(0, False),
                 "w": varchar(63 if base % 2 == 0 else 64, False)}
    source["columnTypes"] = [types[name] for name in fields]
    item["sourceFixture"]["layout"] = layout
    return item


def finite_cases(tag, specification):
    grid = specification["finite"]
    for schema, topology, kind, layout, distinct, predicate in product(*(grid[name] for name in AXES)):
        filter_ = None if predicate == "all" else ["null-test", True, text(2)]
        yield case(tag, schema, topology, kind, columns(layout), where=filter_, distinct=distinct)


def finite_ids(_, specification, _tag):
    expected = {(schema, topo, kind, layout, distinct, filter_) for schema in constants.SCHEMAS
                for topo in ("persistent", "shadow") for kind in ("table", TEMP) for layout in ("text", "mixed")
                for distinct in (False, True) for filter_ in ("all", "present")}
    require(len(expected) == specification["finite"]["identities"], "mysql-create-as-text-grid-size")
    return expected


def finite_id(_, receiver, item):
    definition = receiver["mysqlCreateTableAs"]
    query = definition["query"]
    schema = next(role for role, name in constants.SCHEMAS.items() if query["source"]["schema"] == name)
    role = next(role for role in ("text", "mixed") if columns(role) == query["columns"])
    return (schema, item["sourceFixture"]["topology"], definition["target"][2], role, query["distinct"],
            "all" if query["where"] is None else "present")


def analyzed_request(item, sql, definitions=None):
    if definitions is None:
        fixture = item["sourceFixture"]
        placed = fixture["location"]
        variables = ("v VARCHAR(8191) COLLATE utf8mb4_bin, z VARCHAR(0) COLLATE utf8mb4_bin"
                     if fixture["layout"] == "width-edge" else
                     "n INT NOT NULL DEFAULT 41, m INT, v VARCHAR(8) COLLATE utf8mb4_bin DEFAULT 'v10 ', z VARCHAR(0) COLLATE utf8mb4_bin")
        definitions = [f"CREATE TABLE {placed}({variables})"]
        if fixture["topology"] == "shadow":
            definitions.append(f"CREATE TEMPORARY TABLE {placed}({variables})")
    return {**constants.analyze_request(item, sql), "declarations": definitions}


def wide(tag, overflow=False):
    results = [[text(0), "first_value"], [text(0), "second_value"], [text(1), "c0"], [text(1), "c1"]]
    if overflow:
        results.append([text(1), "c2"])
    return case(tag, "primary", "persistent", "table", results, fields=("v", "z"), layout="width-edge")


def authored(tag):
    target = f"ir_fixture.{constants.NAME}"
    table = f"ir_fixture.{resolved.SOURCE}"
    single = case(tag, "primary", "persistent", "table", [[text(0), "label"]], fields=("v",))
    yield analyzed_request(single, f"CREATE TABLE {target} AS SELECT v AS label FROM {table}"), single

    pair = case(tag, "primary", "persistent", "table", [[text(0), "label"], [text(0), "again"]], fields=("v",))
    yield analyzed_request(pair, f"CREATE TABLE {target} AS SELECT v AS label, {table}.v AS again FROM {table}"), pair

    mixed = case(tag, "primary", "persistent", "table", [[slot(1), "number"], [text(0), "label"]],
                 where=["null-test", True, text(0)], order=[[2, True, False]], limit=[2, 1], fields=("v", "n"))
    yield analyzed_request(mixed, f"CREATE TABLE {target} AS SELECT n AS number, v AS label FROM {table} WHERE v IS NOT NULL ORDER BY 2 DESC LIMIT 2 OFFSET 1"), mixed

    shadow = case(tag, "primary", "shadow", TEMP, [[text(0), "label"]], fields=("v",), distinct=True)
    yield analyzed_request(shadow, f"CREATE TEMPORARY TABLE {target} AS SELECT DISTINCT v AS label FROM {table}"), shadow

    bound = wide(tag)
    query = f"SELECT v AS `first_value`, v AS second_value, z AS c0, z AS c1 FROM {table}"
    yield analyzed_request(bound, f"CREATE TABLE {target} AS {query}"), bound


def retained(tag):
    return case(tag, "primary", "persistent", "table", [[text(1), "a"]], fields=("n", "v"), layout="retained-text")


def negatives(tag):
    diagnostic = constants.DIAGNOSTIC
    yield wide(tag, overflow=True), diagnostic, WIDTH_ERROR
    item = retained(tag)
    body = item["mysqlCreateTableAs"]["query"]
    collated = ["collated-text", COLLATION, text(1)]
    yield constants.replace_query(item, {**body, "columns": [[collated, "a"]]}), diagnostic, resolved.FIELD_ERROR
    yield constants.replace_query(item, {**body, "columns": [[text(0), "a"]]}), diagnostic, SLOT_ERROR
    invalid = f"CREATE TABLE {constants.NAME} AS SELECT v AS a FROM {resolved.SOURCE}"
    yield analyzed_request(item, invalid, [f"CREATE TABLE ir_fixture.{resolved.SOURCE}(v VARCHAR(5))"]),         diagnostic, "The text slot requires an explicit utf8mb4_bin collation."
    yield constants.replace_query(item, {**body, "source": {**body["source"], "relationKind": "view"}}), diagnostic, resolved.SOURCE_ERROR


def seeded(tag, specification, seed):
    rng = Random(seed)
    for index in range(specification["exploration"]["casesPerSeed"]):
        schema, destination = rng.choices(tuple(constants.SCHEMAS), k=2)
        topology = rng.choice(("persistent", "shadow", "temporary-only"))
        kind = rng.choice(("table", TEMP))
        expressions = [text(2), text(3), text(4), slot(0)]
        layout = [[expressions[index % len(expressions)], "a"], [text(rng.choice((2, 3, 4))), "b"]]
        if index % 3 == 0:
            layout += [[text(2), "again"]]
        where = rng.choice((None, ["null-test", True, text(2)], ["null-test", False, text(2)]))
        order = []
        for position in rng.sample(range(1, len(layout) + 1), index % (len(layout) + 1)):
            descending = bool(rng.getrandbits(1))
            order.append([position, descending, not descending])
        limit = rng.choice((None, [1, 0], [2, 0], [1, 1], [2, 2]))
        yield case(tag, schema, topology, kind, layout, where, bool(rng.getrandbits(1)), order, limit,
                   destination=destination, collision=rng.choice(("absent", "persistent", "temporary", "shadow")),
                   guard=bool(rng.getrandbits(1)), variant=index % 5)
