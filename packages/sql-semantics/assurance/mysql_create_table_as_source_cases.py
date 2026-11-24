"""Independent construction cases and native field materialization for CTAS with a table source."""

from copy import deepcopy
from itertools import product
from random import Random

import run_server_mysql_create_table_as as constants
from run import Failure, require
from run_server import same
from run_server_mysql_session_create_table import COLLATION, TEMP, snapshot, table
from server_delete_cases import literal
from server_join_cases import slot
from server_result_cases import key, match, result_window
from server_truth_model import pad_space_distinct_rows, predicate_value

SOURCE = "ir_ctas_source"
AXES = ("sourceSchema", "sourceTopology", "destinationKind", "projection", "distinct", "predicate")
SOURCE_ERROR = "CREATE TABLE AS requires a source-free projection or one resolved table."
FILTER_ERROR = "CREATE TABLE AS requires a constant filter or a checked table predicate."
FIELD_ERROR = "CREATE TABLE AS requires a table slot, an unpadded integral or NULL literal, or its single signed cast."
SEMANTIC_ERROR = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\SemanticException"


def comparison(value=0):
    return ["comparison", ">", slot(0), literal(value)]


def query(schema, fixture, columns, where=None, distinct=False, order=(), limit=None, input_name=SOURCE, fields=("n", "m")):
    source = {"schema": constants.SCHEMAS[schema], "table": input_name, "columns": list(fields)}
    if fixture != "persistent":
        source["relationKind"] = TEMP
    return {**constants.select(columns, where, distinct, order, limit), "source": source}


def construction(tag, schema, fixture, kind, columns, where=None, distinct=False, order=(), limit=None,
                 destination="primary", collision="absent", guard=False, input_name=SOURCE, fields=("n", "m"), variant=0):
    projection = query(schema, fixture, columns, where, distinct, order, limit, input_name, fields)
    item = constants.case(tag, destination, kind, collision, guard, projection, variant)
    return {**item, "sourceFixture": {"location": f"{constants.SCHEMAS[schema]}.{input_name}", "topology": fixture}}


def layout(role):
    return [[slot(0), "a"], [slot(1), "b"]] if role == "normal" else [[slot(1), "b"], [slot(0), "a"]]


def finite_cases(tag, specification):
    dimension = specification["finite"]
    for schema, fixture, kind, projection, distinct, predicate in product(*(dimension[name] for name in AXES)):
        condition = None if predicate == "all" else comparison()
        yield construction(tag, schema, fixture, kind, layout(projection), where=condition, distinct=distinct)


def finite_ids(_, specification, _tag):
    identities = {(schema, lifetime, kind, projection, distinct, predicate)
                  for schema in constants.SCHEMAS for lifetime in ("persistent", "temporary-only", "shadow")
                  for kind in ("table", TEMP) for projection in ("normal", "reversed")
                  for distinct in (False, True) for predicate in ("all", "positive")}
    require(len(identities) == specification["finite"]["identities"], "mysql-create-as-input-grid-size")
    return identities


def finite_id(_, response, item):
    definition = response["mysqlCreateTableAs"]
    query = definition["query"]
    schema = next(role for role, name in constants.SCHEMAS.items() if name == query["source"]["schema"])
    projection = next(role for role in ("normal", "reversed") if layout(role) == query["columns"])
    return (schema, item["sourceFixture"]["topology"], definition["target"][2], projection, query["distinct"],
            "all" if query["where"] is None else "positive")


def declarations(item):
    fixture = item["sourceFixture"]
    _, name = fixture["location"].split(".", 1)
    values = []
    for schema in constants.SCHEMAS.values():
        target = f"{schema}.{name}"
        if target != fixture["location"] or fixture["topology"] != "temporary-only":
            values.append(f"CREATE TABLE {target} (n INT NOT NULL DEFAULT 41, m INT DEFAULT 67)")
        if target == fixture["location"] and fixture["topology"] != "persistent":
            values.append(f"CREATE TEMPORARY TABLE {target} (n INT NOT NULL DEFAULT 122, m INT DEFAULT 148)")
    return values


def analyze(item, sql):
    return {**constants.analyze_request(item, sql), "declarations": declarations(item)}


def authored(tag):
    primary, decoy = constants.SCHEMAS.values()
    destination = f"{primary}.{constants.NAME}"
    aliases = [[slot(0), "a"], [slot(0), "b"]]
    same_slot = construction(tag, "primary", "persistent", "table", aliases, fields=("n",))
    original = f"CREATE TABLE {destination} AS SELECT n AS a, {primary}.{SOURCE}.n AS b FROM {SOURCE}"
    yield analyze(same_slot, original), same_slot

    cross = construction(tag, "decoy", "persistent", "table", layout("normal"), where=comparison())
    yield analyze(cross, f"CREATE TABLE {destination} AS SELECT {SOURCE}.n AS a, m AS b FROM {decoy}.{SOURCE} WHERE n > 0"), cross

    shadow = construction(tag, "primary", "shadow", TEMP, layout("normal"), where=comparison(), distinct=True)
    yield analyze(shadow, f"CREATE TEMPORARY TABLE {destination} AS SELECT DISTINCT n AS a, m AS b FROM {primary}.{SOURCE} WHERE n > 0"), shadow

    overlap = construction(tag, "primary", "persistent", TEMP, [[slot(0), "a"]], where=comparison(),
                           collision="persistent", input_name=constants.NAME, fields=("n",))
    yield analyze(overlap, f"CREATE TEMPORARY TABLE {destination} AS SELECT {constants.NAME}.n AS a FROM {destination} WHERE n > 0"), overlap

    constant = construction(tag, "primary", "persistent", "table", [[literal(1), "c0"]], fields=())
    yield analyze(constant, f"CREATE TABLE {destination} AS SELECT 1 AS c0 FROM {SOURCE}"), constant


def preserved_original(tag):
    return construction(tag, "primary", "persistent", "table", [[literal(1), "c0"]], input_name="known", fields=("metric",))


def negatives(tag):
    diagnostic = constants.DIAGNOSTIC
    item = construction(tag, "primary", "persistent", "table", [[slot(0), "a"]])
    definition = item["mysqlCreateTableAs"]
    shape = definition["query"]
    source = shape["source"]
    view = {**source, "relationKind": "view"}
    yield constants.replace_query(item, {**shape, "source": view}), diagnostic, SOURCE_ERROR
    other = {**source, "table": "other"}
    crossed = {"kind": "CROSS", "left": source, "right": other, "on": None}
    yield constants.replace_query(item, {**shape, "source": crossed}), diagnostic, SOURCE_ERROR
    varchar = {**source, "columns": ["n", "v"], "columnTypes": ["int", ["varchar", 5, "utf8mb4_bin"]]}
    collated = ["collated-text", COLLATION, ["text-slot", 1, 0]]
    yield constants.replace_query(item, {**shape, "source": varchar, "columns": [[collated, "a"]]}), diagnostic, FIELD_ERROR
    yield constants.replace_query(item, {**shape, "columns": [[slot(3), "a"]]}), diagnostic, "slot must belong to the SELECT source"
    yield constants.replace_query(item, {**shape, "columns": [[constants.cast(slot(0)), "a"]]}), diagnostic, FIELD_ERROR
    missing = constants.analyze_request(item, f"CREATE TABLE {constants.NAME} AS SELECT n AS a FROM ir_missing")
    yield {**missing, "declarations": ["CREATE TABLE ir_fixture.ir_unrelated (n INT)"], "complete": True}, \
        SEMANTIC_ERROR, "does not exist"


def seeded(tag, specification, seed):
    rng = Random(seed)
    for index in range(specification["exploration"]["casesPerSeed"]):
        schema, target = rng.choices(tuple(constants.SCHEMAS), k=2)
        fixture = rng.choice(("persistent", "temporary-only", "shadow"))
        kind = rng.choice(("table", TEMP))
        collision = rng.choice(("absent", "persistent", "temporary", "shadow"))
        quantifier = bool(rng.getrandbits(1))
        right = rng.choice((slot(1), slot(1), literal(rng.randrange(-4, 9)), ["null"]))
        fields = [[slot(0), "a"], [right, "b"]]
        if index % 3 == 0:
            fields.append([slot(rng.randrange(2)), "again"])
        if index % 2 == 1:
            fields.reverse()
        condition = rng.choice((None, ["null-test", False, slot(1)], ["null-test", True, slot(1)],
                                comparison(rng.choice((-2, 1, 5)))))
        order = []
        for position in rng.sample(range(1, len(fields) + 1), index % (len(fields) + 1)):
            descending = bool(rng.getrandbits(1))
            order.append([position, descending, not descending])
        bounds = rng.choice((None, [0, 0], [1, 1], [2, 2], [3, 0], [1, 4]))
        yield construction(tag, schema, fixture, kind, fields, condition, quantifier, order, bounds,
                           target, collision, bool(rng.getrandbits(1)), variant=rng.randrange(8))


def ordered_rows(rows):
    return sorted(rows, key=lambda row: tuple((value is not None, value) for value in row))


def varchar_field(name, width, nullable, default=None):
    return [name, ["varchar", width, COLLATION], nullable, None if default is None else ["text", default]]


def input_table(tag, kind, name, base, layout):
    if name == "known":
        return table(tag, kind, [["metric", "INTEGER", False, literal(61)]], [], [[3], [3], [8]])
    fields = [["n", "INTEGER", False, literal(base + 31)], ["m", "INTEGER", True, literal(base + 57)]]
    rows = [[-7, -8], [3, None], [3, None], [3, base + 5], [8, base + 12], [base + 22, None]]
    if layout == "width-edge":
        fields = [varchar_field("v", 8191, True), varchar_field("z", 0, True)]
        rows = [["edge", ""], [None, None]]
    elif layout in ("text", "retained-text"):
        width = 5 if layout == "retained-text" else 8
        fields += [varchar_field("v", width, True, f"v{base} "), varchar_field("z", 0, False, ""),
                   varchar_field("w", 63 if base % 2 == 0 else 64, False, f"w{base}")]
        rows = [[-7, -8, "a\0", "", "first"], [3, None, "a", "", "t"], [3, None, "a ", "", "t"],
                [3, None, "a ", "", "t"], [8, 5, None, "", "tail"], [base + 22, None, "한", "", f"w{base}"]]
    return table(tag, kind, fields, [], ordered_rows(rows))


def initial_state(item):
    definition = item["mysqlCreateTableAs"]
    target = constants.target_name(definition)
    tag, variant = item["release"], item["variant"]
    permanent, session = constants.initial_state(tag, target, item["topology"], variant)
    fixture = item["sourceFixture"]
    location, source_topology = fixture["location"], fixture["topology"]
    layout = fixture.get("layout", "signed")
    _, name = location.split(".", 1)
    for schema_index, schema in enumerate(constants.SCHEMAS.values()):
        source = f"{schema}.{name}"
        if name == constants.NAME and source != location:
            continue
        base = 10 + schema_index * 40 + variant
        permanent[source] = (None if source == location and source_topology == "temporary-only"
                             else input_table(tag, "table", name, base, layout))
        if source == location and source_topology != "persistent":
            session[source] = input_table(tag, TEMP, name, base + 81, layout)
    return permanent, session


def inherited_layout(item, origin):
    query = item["mysqlCreateTableAs"]["query"]
    actual_by_name = {field[0]: field for field in origin["columns"]}
    physical_slots = [actual_by_name[name] for name in query["source"]["columns"]]
    fields = []
    for term, label in query["columns"]:
        fields.append([label, *physical_slots[term[1]][1:]] if term[0] in ("slot", "text-slot")
                      else constants.evaluate_field(item["release"], term, label)[0])
    return physical_slots, fields


def selecting_windows(item, origin):
    slots, heading = inherited_layout(item, origin)
    query = item["mysqlCreateTableAs"]["query"]
    positions = {field[0]: index for index, field in enumerate(origin["columns"])}
    coordinates = [positions[field[0]] for field in slots]
    selected = []
    for row in origin["rows"]:
        scoped = [row[index] for index in coordinates]
        def operand(term):
            return scoped[term[1]] if term[0] in ("slot", "text-slot") else constants.evaluate_field(item["release"], term, "")[1]

        if query["where"] is None or predicate_value(query["where"], operand) is True:
            selected.append([operand(term) for term, _ in query["columns"]])
    text_width = max((len(value) for row in selected for value in row if isinstance(value, str)), default=0)

    def ranking(row, terms):
        components = []
        for slot, descending, nulls_first in terms:
            value = row[slot - 1]
            if value is None:
                components.append((0 if nulls_first else 1, 0))
                continue
            if isinstance(value, str):
                padded = value.ljust(text_width, " ").encode("utf8")
                value = tuple(-byte for byte in padded) if descending else padded
            elif descending:
                value = -value
            components.append((1 if nulls_first else 0, value))
        return tuple(components)

    if text_width == 0 and not any(field[1].startswith("varchar") for field in heading):
        ranking = key
    alternatives = pad_space_distinct_rows(selected) if query["distinct"] else [selected]
    return ranking, [result_window(heading, [list(row) for row in allowed], query["orderBy"], query["limit"], ranking)
                     for allowed in alternatives]


def snapshots(item, rows=None, sql=None):
    definition = item["mysqlCreateTableAs"]
    kind, target = definition["target"][2], constants.target_name(definition)
    location = item["sourceFixture"]["location"]
    permanent, session = initial_state(item)
    before = snapshot(permanent, session, [0], [0])
    temporary = kind == TEMP
    destination = session if temporary else permanent
    collision = destination.get(target) is not None
    optional = definition["ifNotExists"]
    diagnostics = [] if not collision else [["Note" if optional else "Error", 1050, f"Table '{constants.NAME}' already exists"]]
    total = 0
    if not collision:
        ranking, windows = selecting_windows(item, before["visible"][location])
        window = windows[0]
        total = window["total"]
        if rows is None:
            rows = [row for band in window["bands"] for row in band["members"][:band["multiplicity"]]]
        else:
            ordering = definition["query"]["orderBy"]
            scoped = sorted(rows, key=lambda row: ranking(row, ordering))
            for alternative in windows:
                try:
                    match(alternative, {"status": "rows", "fields": alternative["fields"], "rows": scoped}, sql)
                except Failure as error:
                    if error.signal != "meaning-mismatch":
                        raise
                else:
                    break
            else:
                raise Failure("meaning-mismatch", {"sql": sql, "allowed": windows, "observed": rows})
        destination[target] = {"kind": kind, "engine": "InnoDB", "collation": COLLATION, "columns": window["fields"],
                               "key": [], "rows": ordered_rows(rows)}
    effect = ["db-error", "42S01", 1050] if collision and not optional else ["executed", total]
    pending = [0, 7]
    committed = [0] if temporary else pending
    after = snapshot(permanent, session, pending, committed)
    if temporary and not collision:
        destination[target] = {**destination[target], "rows": []}
    return {"status": constants.LABEL, "initial": before, "effect": effect, "diagnostics": diagnostics,
            "warningCount": len(diagnostics), "active": temporary, "after": after,
            "restored": snapshot(permanent, session, committed, committed),
            "afterDisconnect": snapshot(permanent, {}, committed, committed)}


def observed_rows(item, actual):
    definition = item["mysqlCreateTableAs"]
    target = constants.target_name(definition)
    temporary = definition["target"][2] == TEMP
    scope = "visible" if temporary else "permanent"
    relation = actual["after"][scope][target]
    return None if relation is None else relation["rows"]


def check_observation(item, actual, sql):
    require(actual["status"] == constants.LABEL, "mysql-create-as-input-observer-rejected", {"sql": sql, "observed": actual})
    compared = actual
    if item["sourceFixture"].get("layout", "signed") != "signed":
        compared = deepcopy(actual)
        for phase in ("initial", "after", "restored", "afterDisconnect"):
            for namespace in ("visible", "permanent"):
                for relation in compared[phase][namespace].values():
                    if relation is not None:
                        relation["rows"] = ordered_rows(relation["rows"])
    try:
        expected = snapshots(item, observed_rows(item, compared), sql)
        same(expected, compared)
    except Failure as error:
        if error.signal != "meaning-mismatch":
            raise
        expected = snapshots(item)
        raise Failure(error.signal, {"sql": sql, "expected": expected, "observed": actual}) from error
