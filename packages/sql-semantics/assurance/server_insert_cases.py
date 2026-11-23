"""Build literal INSERT cases and compute independent transactional effects."""

import itertools
import random

from evidence_transcript import canonical
from mysql_physical_text_admission import negative as mysql_text_negative
from server_cases import SCHEMAS

INITIAL = {"target": [[2, 3, 4], [2, 3, 4], [None, 0, 7]],
           "control": [[99, -2, 3], [5, 6, 2]], "events": []}
DEFAULTS = {"id": 83, "delta": -29, "stamp": 9}
LAYOUTS = [["id", "delta"], ["delta", "id"], ["id"], ["delta"]]
VECTORS = [
    [["0", "1"], ["-2", "4"]],
    [[None, "-2"], ["5", None]],
    [["-2147483648", "2147483647"], ["2147483647", "-2147483648"]],
    [["2", "3"], ["2", "7"]],
]


def case(engine, release, columns, rows):
    return {
        "dialect": engine, "release": release,
        "target": {"schema": SCHEMAS[engine], "table": "ir_insert_target", "columns": columns,
                   **({"only": False} if engine == "postgres" else {})},
        "rows": rows,
    }


def finite_cases(engine, tag):
    for layout, count, vector in itertools.product(LAYOUTS, (1, 2), VECTORS):
        yield case(engine, tag, layout, [line[:len(layout)] for line in vector[:count]])


def finite_id(_, response, _item):
    columns, rows = response["target"]["columns"], response["rows"]
    variant = next(index for index, pair in enumerate(VECTORS)
                   if [line[:len(columns)] for line in pair[:len(rows)]] == rows)
    return LAYOUTS.index(columns), len(rows), variant


def finite_ids(_, specification, _tag):
    space = specification["finite"]
    return set(itertools.product(range(len(space["targetLayouts"])), space["rowCounts"], range(len(space["valueVectors"]))))


def seeded(engine, tag, seed, count):
    rng = random.Random(seed)
    values = ["-2147483648", "-7", "-1", "0", "1", "3", "2147483647", None]
    for _ in range(count):
        columns = rng.sample(list(DEFAULTS), rng.randint(1, 3))
        rows = []
        for _ in range(rng.randint(1, 6)):
            toward_duplicate = rng.getrandbits(1)
            row = rng.choice(rows) if rows and toward_duplicate else [rng.choice(values) for _ in columns]
            rows.append(row)
        yield case(engine, tag, columns, rows)


def declarations(engine):
    schema = SCHEMAS[engine]
    return [f"CREATE TABLE {schema}.ir_insert_target (id INTEGER DEFAULT 83, delta INTEGER DEFAULT -29, stamp INTEGER DEFAULT 9)"]


def analyzed(engine, tag):
    start = f"INSERT INTO {SCHEMAS[engine]}.ir_insert_target"
    for tail, columns, rows in [
        ("(delta,id) VALUES (-3,4),(NULL,4)", ["delta", "id"], [["-3", "4"], [None, "4"]]),
        ("(id) VALUES (-2147483648),(2147483647)", ["id"], [["-2147483648"], ["2147483647"]]),
        ("(stamp,delta) VALUES (0,NULL),(0,NULL)", ["stamp", "delta"], [["0", None], ["0", None]]),
    ]:
        yield {"dialect": engine, "release": tag, "sql": start + " " + tail, "declarations": declarations(engine)}, case(
            engine, tag, columns, rows)


def negatives(engine, tag):
    schema = SCHEMAS[engine]
    yield case(engine, tag, [], [["1"]]), "between one and 1024 target columns"
    yield case(engine, tag, ["id"], []), "needs a nonempty list of rows"
    yield case(engine, tag, ["id"], [[]]), "needs nonempty cells"
    yield case(engine, tag, ["id", "delta"], [["1"]]), "must match the target width"
    yield case(engine, tag, ["id", "id"], [["1", "2"]]), "cannot repeat a column name"
    generated = f"CREATE TABLE {schema}.ir_insert_restricted (k INTEGER, n INTEGER GENERATED ALWAYS AS (k) STORED)"
    generated_diagnostic = {"mysql": "generated", "postgres": "cannot insert a non-DEFAULT value"}
    boundary = generated_diagnostic[engine]
    if tag == "mysql-5.6.51":
        text_type, boundary = mysql_text_negative()
        generated = f"CREATE TABLE {schema}.ir_insert_restricted (k INTEGER, n {text_type})"
    restricted = f"INSERT INTO {schema}.ir_insert_restricted (n) VALUES (1)"
    yield {"dialect": engine, "release": tag, "declarations": [generated], "sql": restricted}, boundary
    yield {"dialect": engine, "release": tag, "declarations": declarations(engine),
           "sql": f"INSERT INTO {schema}.ir_insert_target (unknown_column) VALUES (1)"}, "unknown_column"
    yield case(engine, tag, ["id"], [["2147483648"]]), "outside the signed 32-bit range"
    if engine == "postgres":
        using = f"CREATE TABLE {schema}.ir_insert_parent (n INTEGER GENERATED ALWAYS AS IDENTITY)"
        for schema_sql in (
            [f"CREATE TABLE {schema}.ir_insert_restricted (n INTEGER GENERATED ALWAYS AS IDENTITY)"],
            [using, f"CREATE TABLE {schema}.ir_insert_restricted (LIKE {schema}.ir_insert_parent INCLUDING IDENTITY)"],
            [using + " PARTITION BY RANGE (n)", f"CREATE TABLE {schema}.ir_insert_restricted PARTITION OF {schema}.ir_insert_parent DEFAULT"],
        ):
            yield {"dialect": engine, "release": tag, "declarations": schema_sql, "sql": restricted}, "writable signed integer"


def ordered(snapshot):
    return {name: sorted(rows, key=canonical) for name, rows in snapshot.items()}


def observation(effect):
    if effect["status"] != "inserted":
        return effect
    return {**effect, "after": ordered(effect["after"]), "restored": ordered(effect["restored"])}


def expected(item):
    columns = item["target"]["columns"]
    appended = []
    for row in item["rows"]:
        data = {**DEFAULTS, **{name: int(value) if value is not None else None for name, value in zip(columns, row)}}
        appended.append(list(data.values()))
    after = {"target": INITIAL["target"] + appended, "control": INITIAL["control"],
             "events": [["ir_insert_target", *row] for row in appended]}
    return observation({"status": "inserted", "count": len(appended), "columns": 0, "after": after,
                        "restored": INITIAL})


def requests(engine, tag, specification):
    for index, item in enumerate(finite_cases(engine, tag)):
        yield f"finite/{index}", item, item, None
    exploration = specification["exploration"]
    for seed in exploration["seeds"]:
        for index, item in enumerate(seeded(engine, tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, meaning) in enumerate(analyzed(engine, tag)):
        yield f"analysis/{index}", item, meaning, None
    for index, (item, reason) in enumerate(negatives(engine, tag)):
        yield f"negative/{index}", item, None, reason
