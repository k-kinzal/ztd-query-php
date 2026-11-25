"""Independent MySQL field layout and non-strict conversion for explicit CTAS declarations."""

from itertools import product
from random import Random

import run_server_mysql_create_table_as as constants
from run import require
from run_server_mysql_session_create_table import COLLATION, TEMP, snapshot, table, value
from server_delete_cases import literal

AXES = ("destinationKind", "topology", "ifNotExists", "declaration", "body")
STORAGE = {"TINYINT": 8, "SMALLINT": 16, "MEDIUMINT": 24, "INTEGER": 32, "BIGINT": 64}
NAME_ERROR = "CREATE TABLE AS cannot repeat an explicit column name."
LIST_ERROR = "CREATE TABLE AS requires an ordered list of explicit columns."
SIGNED_ERROR = "Explicit CREATE TABLE AS definitions require signed storage."
SOURCE_ERROR = "Explicit CREATE TABLE AS definitions require a source-free projection."
KEY_ERROR = "Explicit CREATE TABLE AS definitions do not support a primary key."


def layout(role):
    return {
        "append": [["other", "SMALLINT", True, literal(7)]],
        "override": [["N", "TINYINT", False, literal(11)]],
        "required-prefix": [["pre", "SMALLINT", False, None]],
        "mixed": [["N", "MEDIUMINT", True, None], ["pre", "SMALLINT", False, literal(7)],
                  ["P", "SMALLINT", False, None], ["extra", "BIGINT", True, None]],
    }[role]


def projection(role):
    terms = [[["null"], "n"], [constants.cast(["null"]), "p"]] if role == "null" else [[literal(172), "n"], [literal(-72100), "p"]]
    return constants.select(terms, where=["boolean", False] if role == "zero-rows" else None)


def case(tag, kind, topology, guard, declarations, query, schema="primary", variant=0):
    item = constants.case(tag, schema, kind, topology, guard, query, variant)
    item["mysqlCreateTableAs"]["declaredColumns"] = declarations
    return item


def finite_cases(tag, specification):
    grid = specification["finite"]
    for kind, topology, guard, declaration, body in product(*(grid[name] for name in AXES)):
        yield case(tag, kind, topology, guard, layout(declaration), projection(body))


def finite_ids(_, specification, _tag):
    expected = {(kind, topology, guard, layout, body) for kind in ("table", TEMP)
                for topology in ("absent", "persistent", "temporary", "shadow") for guard in (False, True)
                for layout in ("append", "override", "required-prefix", "mixed")
                for body in ("zero-rows", "values", "null")}
    require(len(expected) == specification["finite"]["identities"], "mysql-create-as-declaration-grid-size")
    return expected


def finite_id(_, response, item):
    seen = response["mysqlCreateTableAs"]
    role = next(name for name in ("append", "override", "required-prefix", "mixed") if seen["declaredColumns"] == layout(name))
    body = next(name for name in ("zero-rows", "values", "null") if seen["query"] == projection(name))
    return (seen["target"][2], item["topology"], seen["ifNotExists"], role, body)


def authored(tag):
    target = "ir_fixture." + constants.NAME
    item = case(tag, "table", "absent", False, [["other", "INTEGER", True, None]], constants.select([[literal(1), "c0"]]))
    yield constants.analyze_request(item, f"CREATE TABLE {constants.NAME} (other INT) AS SELECT 1 AS c0"), item

    first, next_ = [["N", "SMALLINT", True, literal(52)], ["extra", "BIGINT", False, literal(17)]]
    projection_ = constants.select([[literal(120), "n"], [literal(-11), "v"]])
    item = case(tag, "table", "absent", False, [first, next_], projection_)
    yield constants.analyze_request(item, f"CREATE TABLE {target} (N SMALLINT DEFAULT 52, extra BIGINT NOT NULL DEFAULT 17) AS SELECT 120 AS n, -11 AS v"), item

    item = case(tag, "table", "absent", False, [["pre", "INTEGER", False, None]],
                constants.select([[literal(17), "n"]], where=["boolean", False]))
    yield constants.analyze_request(item, f"CREATE TABLE {target} (pre INT NOT NULL) AS SELECT 17 AS n FROM DUAL WHERE FALSE"), item

    item = case(tag, "table", "absent", False, layout("override"), constants.select([[["null"], "n"]]))
    yield constants.analyze_request(item, f"CREATE TABLE {target} (N TINYINT NOT NULL DEFAULT 11) AS SELECT NULL AS n"), item

    target = "ir_ctas_decoy." + constants.NAME
    item = case(tag, TEMP, "absent", False, layout("mixed"), projection("values"), schema="decoy")
    yield constants.analyze_request(item, f"CREATE TEMPORARY TABLE {target} (N MEDIUMINT, pre SMALLINT NOT NULL DEFAULT 7, P SMALLINT NOT NULL, extra BIGINT) ENGINE=InnoDB AS SELECT 172 AS n, -72100 AS p"), item


def capacity(tag, count=127):
    fields = [[f"f{slot}", "INTEGER", True, None] for slot in range(count)]
    return case(tag, "table", "absent", False, fields, constants.select([[literal(1), "c0"]]))


def replace_declarations(item, columns):
    return {**item, "mysqlCreateTableAs": {**item["mysqlCreateTableAs"], "declaredColumns": columns}}


def negatives(tag):
    item = case(tag, "table", "absent", False, layout("append"), projection("values"))
    field = layout("append")[0]
    yield replace_declarations(item, {"owned": field}), LIST_ERROR
    yield replace_declarations(item, ["bad value"]), LIST_ERROR
    yield replace_declarations(item, [field, ["OTHER", "SMALLINT", True, None]]), NAME_ERROR
    amendment = constants.native_width_amendment()
    yield capacity(tag, amendment["combined"]["replacementExplicitWidth"]), amendment["message"]
    yield replace_declarations(item, [["n", ["varchar", 3, COLLATION], True, None]]), SIGNED_ERROR
    query = {**item["mysqlCreateTableAs"]["query"], "source": {"schema": "ir_fixture", "table": "known", "columns": ["n"]}}
    yield constants.replace_query(item, query), SOURCE_ERROR
    yield constants.analyze_request(item, f"CREATE TABLE {constants.NAME} (c0 INT NOT NULL, PRIMARY KEY (c0)) AS SELECT 1 AS c0"), KEY_ERROR
    yield constants.analyze_request(item, f"CREATE TABLE {constants.NAME} (c0 INT) IGNORE AS SELECT 1 AS c0"), constants.BODY_ERROR


def seeded(tag, specification, seed):
    rng = Random(seed)
    fields = ("n", "p", "q", "x")
    for index in range(specification["exploration"]["casesPerSeed"]):
        names = rng.sample([*fields, "pre", "other", "tail"], rng.randrange(1, 7))
        headings = rng.sample(fields, rng.randrange(1, len(fields) + 1))
        definitions = []
        for name in names:
            storage = rng.choice(tuple(STORAGE))
            nullable = bool(rng.getrandbits(1))
            allowed = (None, literal(0), literal(-11), literal(7), ["null"] if nullable else literal(33))
            definitions.append([name.upper() if rng.getrandbits(1) else name, storage, nullable, rng.choice(allowed)])
        leaves = [[rng.choice((literal(-2147483648), literal(2147483647), literal(-32769), literal(128),
                               literal(-5), ["null"], constants.cast(["null"]))), name] for name in headings]
        where = rng.choice((None, ["boolean", True], ["boolean", False], ["null"]))
        bounds = rng.choice((None, [0, 0], [1, 0], [5, 1]))
        yield case(tag, rng.choice(("table", TEMP)), rng.choice(("absent", "persistent", "temporary", "shadow")),
                   bool(rng.getrandbits(1)), definitions, constants.select(leaves, where=where, limit=bounds),
                   rng.choice(tuple(constants.SCHEMAS)), variant=index % 7)


def projected_values(release, kind, definition):
    query = definition["query"]
    known = {column[0].lower(): column for column in definition["declaredColumns"]}
    selected = {alias.lower() for _, alias in query["columns"]}
    extra = [column for name, column in known.items() if name not in selected]
    rows = constants.materialized_table(release, kind, query)["rows"]
    native_columns = table(release, kind, extra, [], [])["columns"]
    warning = []
    converted = []
    for name, _, nullable, default in extra:
        if default is None and not nullable:
            warning.append(["Warning", 1364, f"Field '{name}' doesn't have a default value"])
            converted.append(0)
        else:
            converted.append(value(default))

    for position, (term, alias) in enumerate(query["columns"]):
        declaration = known.get(alias.lower())
        if declaration is None:
            native_columns.append(constants.evaluate_field(release, term, alias)[0])
            if rows:
                converted.append(rows[0][position])
            continue
        _, storage, nullable, default = declaration
        native_columns.extend(table(release, kind, [[alias, storage, nullable, default]], [], [])["columns"])
        if not rows:
            continue
        datum = rows[0][position]
        if datum is None and not nullable:
            warning.append(["Warning", 1048, f"Column '{alias}' cannot be null"])
            datum = 0
        if datum is not None:
            maximum = 2 ** (STORAGE[storage] - 1) - 1
            if not -maximum - 1 <= datum <= maximum:
                warning.append(["Warning", 1264, f"Out of range value for column '{alias}' at row 1"])
                datum = max(-maximum - 1, min(maximum, datum))
        converted.append(datum)
    materialized = {"kind": kind, "engine": "InnoDB", "collation": COLLATION,
                    "columns": native_columns, "key": [], "rows": [converted] if rows else []}
    return materialized, warning


def prediction(item):
    definition = item["mysqlCreateTableAs"]
    tag, target, kind = item["release"], constants.target_name(definition), definition["target"][2]
    permanent, session = constants.initial_state(tag, target, item["topology"], item["variant"])
    initial = snapshot(permanent, session, [0], [0])
    temporary = kind == TEMP
    affected = session if temporary else permanent
    collision = affected.get(target) is not None
    optional = definition["ifNotExists"]
    if collision:
        warning = [["Note" if optional else "Error", 1050, f"Table '{constants.NAME}' already exists"]]
        effect = ["executed", 0] if optional else ["db-error", "42S01", 1050]
    else:
        affected[target], warning = projected_values(tag, kind, definition)
        effect = ["executed", len(affected[target]["rows"])]
    wrote = [0, 7]
    committed = [0] if temporary else wrote
    after = snapshot(permanent, session, wrote, committed)
    if temporary and not collision:
        affected[target] = {**affected[target], "rows": []}
    return {"status": constants.LABEL, "initial": initial, "effect": effect, "diagnostics": warning[:64],
            "warningCount": len(warning), "active": temporary, "after": after,
            "restored": snapshot(permanent, session, committed, committed),
            "afterDisconnect": snapshot(permanent, {}, committed, committed)}
