"""Ordinary VACUUM source fixtures, direct operands and independently observed effects."""

from itertools import product
from random import Random

from run import require
from run_sqlite_create_table import quoted
from sqlite_flag_pragma_cases import result

AXES = ("scopes", "operands", "transaction", "output", "temp")


def text(value):
    return "'" + value.replace("'", "''") + "'"


def operand_form(role, path, labels, depth):
    first, alias, last = labels
    if role == "rewrite":
        return None, None, [], {}, None
    if role == "literal":
        return text(path), ["text", path], [], {}, path
    if role == "numbered":
        return "?" + first, ["parameter", first], [first], {str(int(first)): path}, path
    if role == "joined-path":
        head = ["text", path[0]]
        sql = text(path[0])
        for index in range(1, depth + 1):
            part = path[index:] if index == depth else path[index]
            head = ["concat", head, ["text", part]]
            sql = f"({sql} || {text(part)})"
        return sql, head, [], {}, path
    if role == "typed-null":
        return "NULL", ["null"], [], {}, None
    if role == "typed-integer":
        return "123", ["integer", "123"], [], {}, None
    if role == "empty-text":
        return "''", ["text", ""], [], {}, ""
    if role == "masked-numbered-pair":
        literal = f"CASE WHEN ?{first} THEN ?{alias} ELSE ?{last} END"
        tree = ["case", None, [[["parameter", first], ["parameter", alias]]], ["parameter", last]]
        return literal, tree, [first, alias, last], {str(int(first)): 0, str(int(last)): path}, path
    raise ValueError(role)


def case(scope="implicit", role="rewrite", transaction=False, output="missing", temp="unopened", *,
         attachment="archive", path="snapshot.sqlite", labels=("002", "02", "09"), depth=1, seed=0):
    qualifier = {"implicit": None, "main": "main", "temporary": "temp", "attachment": attachment}[scope]
    schema = "main" if qualifier is None else qualifier
    literal, tree, markers, bindings, file_value = operand_form(role, path, labels, min(depth, len(path) - 1))
    if schema == "temp":
        destination = ["registrations", markers] if markers else ["absent"]
    else:
        destination = ["absent"] if tree is None else ["evaluated", [tree]]
    return {"operation": "vacuum", "qualifier": qualifier, "schema": schema, "path": path,
            "destination": destination, "literal": literal, "markers": markers, "bindings": bindings,
            "fileValue": file_value, "attachment": attachment, "transaction": transaction,
            "output": output, "temp": temp == "warm", "seed": seed}


def source(item, *, alternate=False):
    qualifier = item["qualifier"]
    if qualifier == "temp" and alternate:
        qualifier = "TeMp"
    statement = ("vacuum" if alternate else "VACUUM") + ("" if qualifier is None else " " + quoted(qualifier))
    return statement if item["literal"] is None else statement + " INTO " + item["literal"]


def analysis(item, sql):
    return {"sql": sql, "searchPath": ["main", item["attachment"]], "declarations": [], "catalogComplete": True}


def requests(contract):
    finite = contract["finite"]
    for index, coordinate in enumerate(product(*(finite[key] for key in AXES))):
        item = case(*coordinate)
        yield f"finite/{index}", analysis(item, source(item)), item
        yield f"direct/{index}", item, item
    for seed in contract["exploration"]["seeds"]:
        rng = Random(seed)
        index_scopes = list(finite["scopes"])
        index_roles = list(finite["operands"])
        rng.shuffle(index_scopes)
        rng.shuffle(index_roles)
        for i in range(contract["exploration"]["casesPerSeed"]):
            number = rng.randint(1, 7)
            labels = ("00" + str(number), "0" + str(number), "0" + str(number + rng.randint(1, 20)))
            item = case(index_scopes[(i // 3) % len(index_scopes)], index_roles[i % len(index_roles)],
                        rng.choice(finite["transaction"]), rng.choice(finite["output"]), rng.choice(finite["temp"]),
                        attachment=('', f'attachment "{i}"', f'添付{i}')[i % 3],
                        path=(f"copy {i}.sqlite", f"item'{i}.sqlite", f"写し{i}.sqlite")[i % 3],
                        labels=labels, depth=rng.randint(1, 8), seed=rng.randint(1, 1000))
            yield f"seed/{seed}/{i}", analysis(item, source(item, alternate=i % 2 == 0)), item
            yield f"direct/{seed}-{i}", item, item

    discarded = case("temporary", "masked-numbered-pair", transaction=True)
    discarded["literal"] = "(missing_column + ?002) + (?02 || ?09)"
    yield "analyze/ignored-unresolved-filename", analysis(discarded, source(discarded)), discarded
    yield "direct/ignored-unresolved-filename", discarded, discarded
    null_binding = case("attachment", "numbered")
    null_binding["bindings"] = {"2": None}
    null_binding["fileValue"] = None
    yield "analyze/null-filename-binding", analysis(null_binding, source(null_binding)), null_binding
    yield "direct/null-filename-binding", null_binding, null_binding


def negatives():
    item = case()
    yield analysis(item, 'VACUUM "unknown"'), "rejected", "requires a known schema qualifier"
    yield analysis(item, "VACUUM main INTO missing_column"), "analysis-rejected", "Column missing_column does not exist"
    yield {**item, "destination": ["evaluated", [["text", "one"], ["text", "two"]]]}, "rejected", "exactly one source-free filename"
    yield {**item, "schema": "temp", "destination": ["evaluated", [["text", "name"]]]}, "rejected", "numbered registrations for temp"
    yield {**item, "destination": ["registrations", ["1"]]}, "rejected", "numbered registrations for temp"
    yield {**item, "schema": "temp", "destination": ["registrations", []]}, "rejected", "nonempty list of numbered registrations"
    yield {**item, "schema": "temp", "destination": ["wrong-registration"]}, "rejected", "nonempty list of numbered registrations"


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in AXES)))


def finite_id(measured, domain):
    reply, observation = measured
    choices = [(scope, role) for scope, role in product(domain["scopes"], domain["operands"])
               if source(case(scope, role)) == observation["input"]]
    require(len(choices) == 1 and reply["schema"] == case(*choices[0])["schema"], "sqlite-vacuum-finite-source", choices)
    (scope, role), = choices
    before = observation["before"]
    choice = (scope, role, before["transaction"], before["destination"]["state"],
              "warm" if "temp" in before["schemata"] else "unopened")
    return tuple(domain[key].index(value) for key, value in zip(AXES, choice))


def forms(measured):
    reply, native = measured
    yield "destination/" + reply["destination"][0]
    state = native["before"]["destination"]["state"]
    yield "file/" + state
    scope = reply["schema"]
    if scope == "":
        yield "scope/empty"
    if '"' in scope:
        yield "scope/quote"
    if any(ord(character) > 127 for character in scope):
        yield "scope/unicode"
    if native["statement"]["parameterCount"] > len(native["statement"]["parameters"]):
        yield "parameter/gaps"
    if native["after"]["destination"].get("state") == "copied":
        yield "effect/copied"
    if native["before"]["schemata"] != native["after"]["schemata"]:
        yield "effect/rebuilt"
    if native["statement"]["evaluationError"] is not None:
        yield "effect/runtime-refusal"


def required(domain):
    return ({"destination/absent", "destination/evaluated", "destination/registrations", "scope/empty", "scope/quote",
             "scope/unicode", "parameter/gaps", "effect/copied", "effect/rebuilt", "effect/runtime-refusal"}
            | {"file/" + state for state in domain["output"]})


def attachment_rows(item):
    attached = [(0, "main", ""), (2, item["attachment"], "")]
    if item["temp"]:
        attached.insert(1, (1, "temp", ""))
    return result(rows=attached)["rows"]


def fixture_schema(item, slot, compacted=False):
    name = "entry" + str(item["seed"] + slot)
    rows = ((slot + 3, name), (slot + 7, None))
    return {"header": {"application_id": 300 + slot, "freelist_count": 0 if compacted else 3,
                       "schema_version": 4 if compacted else 3, "user_version": 47 + slot},
            "tables": result(rows=(("entries",),))["rows"], "rows": result(rows=rows)["rows"], "pages": 2 if compacted else 5}


def expected(item):
    schema, slot_map = item["schema"], {"main": 0, **({"temp": 1} if item["temp"] else {}), item["attachment"]: 2}
    temp = schema == "temp"
    before = {name: fixture_schema(item, slot) for name, slot in slot_map.items()}
    after = before.copy()
    destination = initial_destination = {"state": item["output"]}
    has_operand = item["literal"] is not None
    error = None
    if not temp:
        if item["transaction"]:
            error = "cannot VACUUM from within a transaction"
        elif has_operand and item["fileValue"] is None:
            error = "non-text filename"
        elif has_operand and item["fileValue"] != "" and item["output"] == "populated":
            error = "output file already exists"
        elif has_operand and item["fileValue"] == item["path"]:
            destination = {"state": "copied", "database": fixture_schema(item, slot_map[schema], True)}
        elif not has_operand:
            after[schema] = fixture_schema(item, slot_map[schema], True)
    names = {}
    for label in item["markers"]:
        names.setdefault(str(int(label)), "?" + label)
    statement = {**result(), "parameterCount": max((int(key) for key in names), default=0), "parameters": names,
                 "readOnly": temp, "evaluationError": None if error is None else {"code": 1, "message": error}}
    fixed = {"attached": attachment_rows(item), "transaction": item["transaction"]}
    access = [] if temp or error in ("non-text filename", "cannot VACUUM from within a transaction") else [item["fileValue"] or ""]
    return {"statement": statement, "opcode": [] if temp else [[slot_map[schema], has_operand]], "access": access,
            "before": {**fixed, "schemata": before, "destination": initial_destination},
            "after": {**fixed, "schemata": after, "destination": destination}}
