"""Direct and analyzed SQLite foreign-key audit schedules and observations."""
from itertools import product
from random import Random

from run_sqlite_create_table import quoted
from sqlite_flag_pragma_cases import result
from sqlite_fk_audit_fixture import child_values, child_violations, declarations, schemas

AXES = ("selectors", "temp", "childStorage", "parents", "enforcement", "keyLayout")
HEAD = ("table", "rowid", "parent", "fkid")
UNKNOWN = "requires a resolved child or schema catalog"


def role_axes(item):
    attached, child = item["attachment"], item["child"]
    return {
        "implicit-schema": (None, None, ["schema", "main"]),
        "explicit-main": ("main", None, ["schema", "main"]),
        "temporary-schema": ("temp", None, ["schema", "temp"]),
        "attachment-schema": (attached, None, ["schema", attached]),
        "implicit-child": (None, child, ["relation", "temp" if item["temp"] else "main", child, "table"]),
        "main-child": ("main", child, ["relation", "main", child, "table"]),
        "attachment-child": (attached, child, ["relation", attached, child, "table"]),
        "visible-view": ("main", "audit_view", ["relation", "main", "audit_view", "view"]),
        "implicit-temp-catalog": (None, "sqlite_temp_schema", ["catalog", "temp", None]),
        "attachment-catalog": (attached, "sqlite_schema", ["catalog", attached, attached]),
    }


def case(role="implicit-child", temp="unopened", storage="rowid", parent="valid", enforcement=False, layout="single",
         *, attachment="extension", child="child", parent_name="Parent"):
    item = {"operation": "foreign-key-audit", "temp": temp == "shadowed", "storage": storage == "without-rowid",
            "parentMode": parent, "enforcement": enforcement, "keyLayout": layout, "attachment": attachment,
            "child": child, "parent": parent_name}
    schema, argument, target = role_axes(item)[role]
    return {**item, "schema": schema, "argument": argument, "target": target}


def analysis(item, sql, *, complete=True, extra=()):
    return {"sql": sql, "declarations": [*declarations(item), *extra], "catalogComplete": complete,
            "searchPath": ["main", item["attachment"]]}


def source(item, *, uppercase=False, parentheses=True, lexeme=None):
    scope, argument = item["schema"], item["argument"]
    name = quoted("FOREIGN_KEY_CHECK") if uppercase else "foreign_key_check"
    lead = "PRAGMA " + ("" if scope is None else quoted(scope) + ".") + name
    if argument is None:
        return lead
    value = quoted(argument) if lexeme is None else lexeme
    return lead + (f" ( {value} )" if parentheses else f" = {value}")


def requests(specification):
    finite = specification["finite"]
    for index, axes in enumerate(product(*(finite[field] for field in AXES))):
        item = case(*axes)
        yield f"finite/{index}", analysis(item, source(item)), item
        yield f"direct/{index}", item, item
    for seed in specification["exploration"]["seeds"]:
        rng = Random(seed)
        for index in range(specification["exploration"]["casesPerSeed"]):
            role = finite["selectors"][index % len(finite["selectors"])]
            attachment = "" if index % 7 == 0 else rng.choice(("Archiv", 'new "db"', "入庫")) + str(index)
            item = case(role, rng.choice(finite["temp"]), rng.choice(finite["childStorage"]), rng.choice(finite["parents"]),
                        rng.choice(finite["enforcement"]), rng.choice(finite["keyLayout"]), attachment=attachment,
                        child=rng.choice(('receipts "x"', "données", "明細")) + str(index),
                        parent_name=rng.choice(("Original", "in ventory", "一覧")))
            yield f"seed/{seed}/{index}", analysis(item, source(item, uppercase=index % 2 == 0, parentheses=index % 3 == 0)), item
            yield f"direct/{seed}-{index}", item, item
    positive = case("main-child", child="101", layout="composite")
    yield "analyze/numeric-name", analysis(positive, source(positive, lexeme="+101")), positive
    quoted_error = case("main-child", parent="mismatched", child='entry "x"', parent_name='owner "y"')
    yield "analyze/quoted-mismatch", analysis(quoted_error, source(quoted_error)), quoted_error
    yield "direct/quoted-mismatch", quoted_error, quoted_error


def finite_ids(domain):
    return set(product(*(range(len(domain[axis])) for axis in AXES)))


def finite_id(measured, domain):
    _reply, observation = measured
    schema, argument = observation["invocation"][1:]
    aliases = role_axes(case())
    role = next(name for name, value in aliases.items() if value[:2] == (schema, argument))
    initial = "shadowed" if any(row[1][1] == "temp" for row in observation["attachedBefore"]) else "unopened"
    storage, parents, enforcement, layout = observation["fixture"]
    axes = (role, initial, storage, parents, enforcement, layout)
    return tuple(domain[key].index(choice) for key, choice in zip(AXES, axes))


def forms(measured):
    reply, native = measured
    target = reply["target"]
    yield "target/" + target[0]
    if target[1] == "":
        yield "scope/empty"
    if '"' in target[1]:
        yield "scope/quote"
    if target[0] == "relation":
        yield "relation/" + target[3]
    if target[1] in ("main", "temp"):
        yield "domain/" + target[1]
    storage, parent, enforced, layout = native["fixture"]
    yield "storage/" + storage
    yield "parent/" + parent
    yield "layout/" + layout
    yield f"enforcement/{enforced}"
    if "prepareError" not in native["statement"]:
        rows = native["statement"]["rows"]
        if rows and len({str(row) for row in rows}) < len(rows):
            yield "result/multiplicity"
    if native["attachedBefore"] != native["attachedAfter"]:
        yield "effect/native-temp-open"


def required(domain):
    return ({"target/schema", "target/relation", "target/catalog", "relation/table", "relation/view", "scope/empty", "scope/quote",
             "domain/main", "domain/temp", "result/multiplicity", "effect/native-temp-open"}
            | {"storage/" + name for name in domain["childStorage"]} | {"parent/" + name for name in domain["parents"]}
            | {"layout/" + name for name in domain["keyLayout"]} | {"enforcement/True", "enforcement/False"})


def negatives():
    item = case()
    yield analysis(item, "PRAGMA unknown.foreign_key_check"), "rejected", "requires a known schema qualifier"
    yield analysis(item, "PRAGMA main.foreign_key_check(missing_child)"), "rejected", UNKNOWN
    yield analysis(item, "PRAGMA foreign_key_check(child)", complete=False), "rejected", "cannot skip an open relation namespace"
    yield analysis(item, "PRAGMA main.foreign_key_check(child)", extra=("CREATE TABLE main.child(id INT)",)), \
        "rejected", "Conflicting declared relations"
    yield {**item, "target": ["catalog", "main", "temp"]}, "rejected", "catalog access must match"
    yield {**item, "target": ["catalog", "extension", None]}, "rejected", "cannot select an attached builtin catalog"
    yield {**item, "catalogAccess": "main"}, "rejected", "catalog access must match"


def expected(item):
    target = item["target"]
    schema = target[1]
    before = [(0, "main", "")]
    if item["temp"]:
        before.append((1, "temp", ""))
    after = before.copy()
    if not item["temp"] and (target == ["schema", "temp"] or target == ["catalog", "temp", "temp"]):
        after.append((1, "temp", ""))
    attached = (2, item["attachment"], "")
    index = dict(schemas(item)).get(schema)
    examines = index is not None and (target[0] == "schema" or (target[0] == "relation" and target[3] == "table"))
    if examines and item["parentMode"] == "mismatched":
        child, parent = quoted(item["child"]), quoted(item["parent"])
        statement = {"prepareError": f"SQLite 1: foreign key mismatch - {child} referencing {parent}"}
        slots = []
    else:
        values = list(child_violations(item, index)) if examines else []
        if examines and target[0] == "schema":
            values.append(("secondary_child", index + 50, "sponsor", 0))
        statement = result(HEAD, values)
        statement["rows"] = sorted(statement["rows"])
        slots = [{"main": 0, "temp": 1, item["attachment"]: 2}[schema]] if examines else []
    population = {db: result(rows=child_values(item, offset))["rows"] for db, offset in schemas(item)}
    return {"statement": statement, "slots": slots, "attachedBefore": result(rows=(*before, attached))["rows"],
            "attachedAfter": result(rows=(*after, attached))["rows"],
            "populationBefore": population, "populationAfter": population,
            "fixture": ["without-rowid" if item["storage"] else "rowid", item["parentMode"], item["enforcement"], item["keyLayout"]],
            "enforcementAfter": item["enforcement"]}
