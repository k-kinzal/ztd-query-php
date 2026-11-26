"""Independent keys, partial membership, and native uniqueness for SQLite index declarations."""

from itertools import product
from random import Random

from run_sqlite_create_table import quoted
from run_sqlite_scalars import evaluate
from sqlite_add_column_cases import SCHEMAS, error, operand_slots, statement
from sqlite_engine import typed
from sqlite_generated_column_cases import arithmetic, column, integer

TABLE = "ledger"
INDEX = "tag"
HEADING = ("id", "code", "amount", "note")
AXES = ("schemas", "keys", "predicates", "unique", "existing", "population")
FIELDS = ("operation", "schema", "index", "table", "terms", "where", "unique", "ifNotExists")
CASEFOLD = str.maketrans("ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")
STORED = {
    "main": ((11, "A", 1, "main"), (12, "a", -2, "main"), (13, None, 3, "main"), (14, "A ", 4, "main")),
    "temp": ((31, "A", 7, "temp"), (32, "a", -3, "temp"), (33, None, 3, "temp"), (34, "b", None, "temp")),
}
INSERTED = ((80, "A", 3, "write"), (81, "a ", -2, "write"), (82, None, 5, "write"),
            (83, None, 4, "write"), (84, "A", -7, "write"), (85, "a", 7, "write"))


def comparison(operator, left, right):
    return ["comparison", operator, left, right]


def key(role):
    return {
        "amount": [column(2), False],
        "inherited-code": [column(1), False],
        "nocase-code-desc": [["collate", "NOCASE", column(1)], True],
        "sum-constant": [arithmetic("+", column(2), integer(7)), False],
    }[role]


def restriction(role):
    return {"all": None, "positive": comparison(">", column(2), integer(0)),
            "code-present": comparison("IS NOT", column(1), ["null"]),
            "folded": ["folded-membership", True, ["09", "001", "009"]]}[role]


def case(schema="main", term="amount", predicate="all", unique=False, existing="absent", population="mixed",
         *, heading=HEADING, index=INDEX, guarded=None):
    return {
        "operation": "create-index", "schema": schema, "table": TABLE, "index": index, "heading": list(heading),
        "terms": [key(term)], "where": restriction(predicate), "unique": unique,
        "ifNotExists": existing == "guarded" if guarded is None else guarded,
        "population": population, "existing": existing == "guarded",
    }


def binding_projection(item):
    operands = [*(expression for expression, _ in item["terms"]), item["where"]]
    slots = set().union(*(operand_slots(operand) for operand in operands))
    return [[slot, item["heading"][slot]] for slot in sorted(slots)]


def finite(contract):
    for choices in product(*(contract[axis] for axis in AXES)):
        yield case(*choices)


def finite_ids(contract):
    return set(product(*(range(len(contract[axis])) for axis in AXES)))


def finite_id(measured, contract):
    reply, observation = measured
    schema = reply["schema"]
    before = observation["before"][schema]
    choices = (schema, next(name for name in contract["keys"] if [key(name)] == reply["terms"]),
               next(name for name in contract["predicates"] if restriction(name) == reply["where"]),
               reply["unique"], "guarded" if before["indexList"] else "absent",
               "mixed" if before["rows"] else "empty")
    return tuple(contract[axis].index(choice) for axis, choice in zip(AXES, choices))


def explored(contract, seed, amount):
    rng = Random(seed)
    for i in range(amount):
        width = 4 + i % 5
        heading = (*HEADING, *(f'{slot} "列"' for slot in range(4, width)))
        selected = rng.choice(SCHEMAS)
        value = case(selected, predicate=rng.choice(contract["predicates"]), unique=bool(rng.getrandbits(1)),
                     existing="guarded" if i % 5 == 0 else "absent", population=contract["population"][i % 2],
                     heading=heading, index=f'{i}"名' if i % 2 else f"index_{i}",
                     guarded=True if i % 5 == 0 else bool(rng.getrandbits(1)))
        if i == amount - 1:
            value["terms"] = [[integer(rng.randrange(-50, 51)), bool(rng.getrandbits(1))]]
            value["where"] = None
            yield value
            continue
        terms = []
        for position in range(1 + i % 3):
            role = (i + position) % 6
            operand = column(rng.randrange(width))
            if role in (1, 2):
                operand = ["collate", ("BINARY", "NOCASE", "RTRIM")[(i + position) % 3], column(1)]
            elif role in (3, 4):
                operand = arithmetic("+", column(2), integer(rng.randrange(1, 10)))
                if role == 4:
                    operand = arithmetic("-", operand, arithmetic("+", column(0), integer(2)))
            elif role == 5:
                operand = ["folded-membership", True, ["007", "7", "01"]]
            terms.append([operand, (i + position) % 2 != 0])
        value["terms"] = terms
        yield value


def declarations(item):
    result = []
    for schema in SCHEMAS:
        fields = [f"{quoted(item['heading'][0])} INTEGER PRIMARY KEY",
                  f"{quoted(item['heading'][1])} TEXT COLLATE {'NOCASE' if schema == 'main' else 'BINARY'}",
                  f"{quoted(item['heading'][2])} INTEGER", f"{quoted(item['heading'][3])} TEXT"]
        fields.extend(f"{quoted(name)} INTEGER" for name in item["heading"][4:])
        result.append(f"CREATE TABLE {schema}.{quoted(item['table'])} ({', '.join(fields)})")
    return result


def authored():
    yield case("temp", "inherited-code"), "CREATE INDEX tag ON ledger('CODE')"
    yield case("main", "nocase-code-desc", "positive", True), 'CREATE UNIQUE INDEX MAIN.tag ON LEDGER(("Code" COLLATE NOCASE) DESC) WHERE amount > 0'
    yield case("temp", "sum-constant", "code-present"), "CREATE INDEX TEMP.tag ON ledger(amount + 7 ASC) WHERE code IS NOT NULL"
    yield case("main", predicate="folded", unique=True, existing="guarded"), "CREATE UNIQUE INDEX IF NOT EXISTS main.tag ON ledger(amount) WHERE (?09+?001+?009) NOT IN ()"
    value = case("temp", predicate="folded")
    value["terms"] = [[["folded-membership", True, ["005", "5", "001"]], False]]
    yield value, "CREATE INDEX tag ON ledger((?005+?5+?001) NOT IN ()) WHERE (?09+?001+?009) NOT IN ()"
    value = case("main")
    value["terms"] = [[["cast", "TEXT", ["text", "token"]], True]]
    yield value, "CREATE INDEX main.tag ON ledger(CAST('token' AS TEXT) DESC)"
    value = case("main")
    value["where"] = comparison(">=", column(0), integer(13))
    yield value, "CREATE INDEX main.tag ON ledger(amount) WHERE rowid >= 13"
    value = case("main", unique=True, predicate="code-present")
    value["terms"] = [[["collate", "RTRIM", column(1)], False], [column(2), True], [column(1), False]]
    yield value, "CREATE UNIQUE INDEX main.tag ON ledger(code COLLATE RTRIM, amount DESC, code) WHERE ledger.code IS NOT NULL"
    value = case("temp", population="empty", index='entry "列"')
    value["terms"] = [[["collate", "NOCASE", column(1)], False], [arithmetic("-", arithmetic("+", column(2), integer(7)), column(0)), True]]
    yield value, 'CREATE INDEX temp."entry ""列""" ON ledger((\'code\') COLLATE NOCASE, ((amount+7)-id) DESC)'
    yield case("main", "inherited-code", population="mixed", unique=True), "CREATE UNIQUE INDEX main.tag ON ledger(code)"
    for matches, keyword in ((True, "NOT IN"), (False, "IN")):
        value = {**case("main", unique=True), "where": ["folded-membership", matches, []]}
        yield value, f"CREATE UNIQUE INDEX main.tag ON ledger(amount) WHERE amount {keyword} ()"


def requests(specification):
    domain = specification["finite"]
    for index, value in enumerate(finite(domain)):
        yield f"finite/{index}", value, value
    for seed in specification["exploration"]["seeds"]:
        for index, value in enumerate(explored(domain, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", value, value
    for index, (value, sql) in enumerate(authored()):
        yield f"analyze/{index}", {"sql": sql, "declarations": declarations(value)}, value


def missing_slot():
    return {**case(), "terms": [[column(5), False]]}


def negatives():
    value = case()
    yield {**value, "terms": []}, "rejected", "requires a nonempty ordered key list"
    yield {**value, "terms": {"1": value["terms"][0]}}, "rejected", "requires a nonempty ordered key list"
    yield {**value, "terms": [False]}, "rejected", "requires a nonempty ordered key list"
    yield missing_slot(), "rejected", "visible occurrence"
    yield {**value, "where": comparison(">", column(5), integer(0))}, "rejected", "visible occurrence"
    yield {**value, "terms": [[arithmetic("+", column(0), ["parameter", "5"]), False]]}, "rejected", "cannot contain a parameter"
    yield {**value, "terms": [[["text", "ambiguous"], False]]}, "rejected", "index name position"
    yield {**value, "terms": [[["collate", "BINARY", ["text", "ambiguous"]], False]]}, "rejected", "index name position"
    yield {**value, "heading": ["id", "ID"]}, "rejected", "distinct declared column names"
    yield {**value, "index": "SQLite_reserved"}, "rejected", "reserved SQLite namespace"
    for sql, outcome, message, extra in (
        ("CREATE INDEX main.tag ON missing(code)", "analysis-rejected", "Relation missing does not exist", ()),
        ("CREATE INDEX main.tag ON v(code)", "analysis-rejected", "only a table", ("CREATE VIEW main.v AS SELECT code FROM main.ledger",)),
        ("CREATE INDEX main.tag ON ledger(unknown)", "analysis-rejected", "Column unknown does not exist", ()),
        ("CREATE INDEX main.tag ON ledger(code) WHERE unknown>0", "analysis-rejected", "Column unknown does not exist", ()),
        ("CREATE INDEX main.tag ON ledger(ledger.amount)", "analysis-rejected", 'operator is prohibited in index expressions', ()),
        ("CREATE INDEX main.tag ON ledger(rowid)", "analysis-rejected", "Column rowid does not exist", ()),
        ("CREATE INDEX main.tag ON ledger(?07)", "analysis-rejected", "Parameters are prohibited in index", ()),
        ("CREATE INDEX main.tag ON ledger(code) WHERE ?07", "analysis-rejected", "Parameters are prohibited in partial index", ()),
        ("CREATE INDEX main.tag ON ledger(code) WHERE EXISTS (SELECT 1)", "analysis-rejected", "Subqueries are prohibited in partial index", ()),
        ("CREATE INDEX main.tag ON ledger(code NULLS FIRST)", "rejected", "cannot specify NULLS FIRST", ()),
        ("CREATE INDEX main.tag ON ledger(code DESC NULLS LAST)", "rejected", "cannot specify NULLS FIRST", ()),
    ):
        yield {"sql": sql, "declarations": [*declarations(value), *extra]}, outcome, message


def input_rows(item, schema):
    if schema == item["schema"] and item["population"] == "empty":
        return []
    return [(*row, *(ordinal - row[0] for ordinal in range(4, len(item["heading"])))) for row in STORED[schema]]


def writes(item):
    return [(*row, *(ordinal + row[0] for ordinal in range(4, len(item["heading"])))) for row in INSERTED]


def owned_term(expression):
    while expression[0] == "collate":
        expression = expression[2]
    return expression[1] if expression[0] == "column" else None


def collation(item, expression):
    if expression[0] == "collate":
        return expression[1]
    return "NOCASE" if item["schema"] == "main" and owned_term(expression) == 1 else "BINARY"


def typed_key(item, expression, row):
    value = evaluate(expression, {}, row)
    if isinstance(value, str):
        mode = collation(item, expression)
        if mode == "NOCASE":
            return value.translate(CASEFOLD)
        if mode == "RTRIM":
            return value.rstrip(" ")
    return value


def included(predicate, row):
    return predicate is None or evaluate(predicate, {}, row) not in (None, 0)


def duplicates(item, row, previous):
    if not item["unique"] or not included(item["where"], row):
        return False
    shape = [typed_key(item, expression, row) for expression, _ in item["terms"]]
    return None not in shape and any(included(item["where"], old)
        and [typed_key(item, expression, old) for expression, _ in item["terms"]] == shape for old in previous)


def unique_error(item):
    slots = [owned_term(expression) for expression, _ in item["terms"]]
    context = f"index '{item['index']}'" if None in slots else ", ".join(
        f"{item['table']}.{item['heading'][slot]}" for slot in slots)
    return error("UNIQUE constraint failed: " + context)


def markers(term):
    if term is None:
        return
    if term[0] == "folded-membership":
        yield from term[2]
    elif term[0] in ("arithmetic", "comparison"):
        yield from markers(term[2])
        yield from markers(term[3])
    elif term[0] in ("collate", "cast"):
        yield from markers(term[2])


def action_result(item, problem):
    names = {}
    for expression in [*(term[0] for term in item["terms"]), item["where"]]:
        for label in markers(expression):
            names.setdefault(str(int(label)), "?" + label)
    return {**statement(error=problem), "parameters": names, "parameterCount": max(map(int, names), default=0)}


def old_definition(item):
    return {**item, "terms": [[column(3), False]], "where": None, "unique": False}


def index_record(item):
    keys = []
    for position, (expression, descending) in enumerate(item["terms"]):
        slot = owned_term(expression)
        keys.append([position, -2 if slot is None else slot, None if slot is None else item["heading"][slot],
                     int(descending), collation(item, expression), 1])
    keys.append([len(keys), -1, None, 0, "BINARY", 0])
    index = (0, item["index"], int(item["unique"]), "c", int(item["where"] is not None))
    return {"indexList": [[typed(value) for value in index]],
            "keys": [[typed(value) for value in row] for row in keys]}


def state(item, schema, rows, key_definition=None):
    listed = {"indexList": [], "keys": []} if key_definition is None else index_record(key_definition)
    root = [typed("table"), typed(item["table"]), typed(item["table"]), typed(2)]
    index = None if key_definition is None else [typed("index"), typed(item["index"]), typed(item["table"]), typed(3)]
    return {**listed, "root": root, "index": index, "fields": item["heading"],
            "rows": [[typed(cell) for cell in row] for row in rows]}


def expected(item):
    selected = item["schema"]
    rows = input_rows(item, selected)
    old = old_definition(item) if item["existing"] else None
    failure = None if old is not None or not any(duplicates(item, row, rows[:position]) for position, row in enumerate(rows)) else unique_error(item)
    effective = old or (item if failure is None else None)
    sibling = next(schema for schema in SCHEMAS if schema != selected)
    other = state(item, sibling, input_rows(item, sibling))

    def picture(selected_rows, definition):
        return {selected: state(item, selected, selected_rows, definition), sibling: other}

    before = picture(rows, old)
    after = picture(rows, effective)
    future = None
    if failure is None:
        written = rows.copy()
        events = []
        for candidate in writes(item):
            problem = unique_error(effective) if duplicates(effective, candidate, written) else None
            events.append(statement(error=problem, changes=int(problem is None)))
            if problem is None:
                written.append(candidate)
        future = {"writes": events, "state": picture(written, effective),
                  "visible": [[typed(cell) for cell in row] for row in written if included(effective["where"], row)]}
    return {"before": before, "statement": action_result(item, failure), "after": after, "later": future,
            "transaction": True, "rolledBack": before}


def predicate_sql(item):
    term = item["where"] if not item["existing"] else None
    if term is None:
        return ""
    if term[0] == "folded-membership":
        discarded = " + ".join("?" + label for label in term[2]) or "0"
        membership = "NOT IN" if term[1] else "IN"
        return f" WHERE ({discarded}) {membership} ()"
    assert term[0] == "comparison" and term[2][0] == "column"
    literal = "NULL" if term[3] == ["null"] else str(int(term[3][1]))
    return f" WHERE {quoted(item['heading'][term[2][1]])} {term[1]} {literal}"


def forms(measured):
    value, result = measured
    yield "schema/" + value["schema"]
    width = len(result["before"][value["schema"]]["fields"])
    slots = len(value["bindings"])
    yield f"width/{width}"
    yield f"bindings/{'empty' if slots == 0 else 'single' if slots == 1 else 'multiple'}"
    if 0 < slots < width:
        yield "bindings/sparse"
    yield f"keys/{len(value['terms'])}"
    yield f"partial/{value['where'] is not None}"
    yield f"unique/{value['unique']}"
    yield f"guarded/{value['ifNotExists']}"
    for term, descending in value["terms"]:
        yield "key/" + term[0]
        yield f"descending/{descending}"
    yield f"refused/{result['statement']['evaluationError'] is not None}"
    yield f"previous-index/{bool(result['before'][value['schema']]['indexList'])}"


def required(_):
    return {"schema/main", "schema/temp", "keys/1", "keys/2", "keys/3", "partial/True", "partial/False", "unique/True",
            "unique/False", "guarded/True", "guarded/False", "key/column", "key/collate", "key/arithmetic",
            "key/folded-membership", "descending/True", "descending/False", "previous-index/True", "previous-index/False",
            "refused/True", "refused/False", "bindings/empty", "bindings/single", "bindings/multiple", "bindings/sparse", *(f"width/{width}" for width in range(4, 9))}
