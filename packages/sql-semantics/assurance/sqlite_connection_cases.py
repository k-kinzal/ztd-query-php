"""Fixed connection cases and independently modeled per-schema state."""

from itertools import product
from random import Random

from run import require
from sqlite_flag_pragma_cases import result

AXES = ("requests", "temp", "transaction")
ASCII_LOWER = str.maketrans("ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")
STRUCTURE = ("command", "nameOperand", "name", "resolved", "fileOperand", "keyOperand")


def ascii_key(name):
    return name.translate(ASCII_LOWER)


def text(value):
    return "'" + value.replace("'", "''") + "'"


def word(value):
    return text(value), ["text", value], value


def filename_expression(path, depth):
    sql, operand = text(path[0]), ["text", path[0]]
    for index in range(1, min(depth + 1, len(path))):
        part = path[index:] if index == min(depth, len(path) - 1) else path[index]
        sql = f"({sql} || {text(part)})"
        operand = ["concat", operand, ["text", part]]
    return sql, operand, None


def template(item, depth):
    file_path, alias = item["filePath"], item["alias"]
    file_label, name_label, key_label = item["labels"]
    alpha, beta, parked = item["existing"]
    memory = word(":memory:")
    file = word(file_path)
    plain = (*word(alias)[:2], None)
    bare = (f"(({alias}))", ["text", alias], None) if alias == "extension" else plain
    parameter_file = ("?" + file_label, ["parameter", file_label], None)
    parameter_name = ("?" + name_label, ["parameter", name_label], None)
    parameter_key = ("?" + key_label, ["parameter", key_label], None)
    null = ("NULL", ["null"], None)
    integer = ("38", ["integer", "38"], None)
    keys = ("password", ["text", "password"], None)
    bare_file = (file_path, ["text", file_path], file_path)
    return {
        "attach-memory": (memory, bare, None, {}, alias, "", None),
        "attach-file": (file, plain, None, {}, alias, file_path, None),
        "attach-bare-file": (bare_file, bare, None, {}, alias, file_path, None),
        "attach-file-and-key-parameters": (parameter_file, bare, parameter_key,
                                           {str(int(file_label)): file_path, str(int(key_label)): 17}, alias, file_path, None),
        "attach-name-and-key-parameters": (memory, parameter_name, parameter_key,
                                           {str(int(name_label)): alias, str(int(key_label)): None}, alias, "", None),
        "attach-concatenated-file": (filename_expression(file_path, depth), plain, None, {}, alias, file_path, None),
        "attach-null-empty-name": (null, null, None, {}, "", "", None),
        "attach-integer-name": (memory, integer, None, {}, "38", "", None),
        "attach-duplicate-alias": (memory, word(alpha.upper()), None, {}, alpha.upper(), "", None),
        "attach-same-file-new-name": (word(item["knownPath"]), plain, None, {}, alias, item["knownPath"], None),
        "attach-dynamic-reserved-name": (memory, parameter_name, None, {str(int(name_label)): "TeMp"}, "TeMp", "", None),
        "attach-key-literal": (file, plain, keys, {}, alias, file_path, None),
        "detach-bare": (None, (alpha if alpha == "alpha" else text(alpha), ["text", alpha], alpha), None, {}, alpha, None, alpha),
        "detach-case": (None, word(alpha.upper()), None, {}, alpha.upper(), None, alpha),
        "detach-file": (None, word(parked), None, {}, parked, None, parked),
        "detach-numbered": (None, parameter_name, None, {str(int(name_label)): beta}, beta, None, None),
        "detach-dynamic-missing": (None, parameter_name, None, {str(int(name_label)): "absent"}, "absent", None, None),
        "detach-dynamic-temp": (None, parameter_name, None, {str(int(name_label)): "TEMP"}, "TEMP", None, None),
        "detach-integer-name": (None, ("25", ["integer", "25"], None), None, {}, "25", None, "25"),
        "detach-null-name": (None, null, None, {}, "", None, ""),
    }


def case(role="attach-file", temp="unopened", transaction="none", *, alias="extension", file_path="fixture_file",
         known_path="known_file", labels=("003", "002", "007"), existing=("alpha", "beta", "parked"), seed=0, depth=2):
    if role == "attach-bare-file":
        file_path = "fixture_file"
    item = {"operation": "connection-command", "temp": temp == "warm", "transaction": transaction, "alias": alias,
            "filePath": file_path, "knownPath": known_path, "labels": labels, "existing": existing, "seed": seed,
            "empty": role == "detach-null-name"}
    file, name, key, bound, chosen, path, resolved = template(item, depth)[role]
    return {**item, "command": "detach" if file is None else "attach", "nameOperand": name[1],
            "name": chosen if name[1][0] != "parameter" else None, "resolved": resolved,
            "fileOperand": None if file is None else [file[1]], "keyOperand": None if key is None else [key[1]],
            "fileSql": None if file is None else file[0], "nameSql": name[0], "keySql": None if key is None else key[0],
            "auth": name[2] if file is None else file[2], "bind": bound, "chosen": chosen, "nativePath": path}


def source(item, *, optional=False):
    keyword = " DATABASE" if optional else ""
    if item["fileSql"] is None:
        return "DETACH" + keyword + " " + item["nameSql"]
    key = "" if item["keySql"] is None else " KEY " + item["keySql"]
    return "ATTACH" + keyword + " " + item["fileSql"] + " AS " + item["nameSql"] + key


def initial_schemas(item):
    alpha, beta, parked = item["existing"]
    states = [("main", "", 2), (alpha, "", 3), (beta, "", 5), (parked, item["knownPath"], 7), ("25", "", 13)]
    if item["temp"]:
        states.insert(1, ("temp", "", 11))
    if item["empty"]:
        states.append(("", "", 23))
    return states


def analysis(item, sql):
    known = [schema for schema, _, _ in initial_schemas(item) if schema != "temp"]
    return {"sql": sql, "declarations": [], "catalogComplete": True, "searchPath": known}


def requests(contract):
    finite = contract["finite"]
    for index, coordinate in enumerate(product(*(finite[key] for key in AXES))):
        item = case(*coordinate)
        yield f"finite/{index}", analysis(item, source(item)), item
        yield f"direct/{index}", item, item
    for seed in contract["exploration"]["seeds"]:
        rng = Random(seed)
        order = finite["requests"].copy()
        rng.shuffle(order)
        for index in range(contract["exploration"]["casesPerSeed"]):
            slot = rng.randint(1, 10)
            alias = ("", 'new "db"', "添付")[index % 3] + ("" if index % 3 == 0 else str(index))
            ext = ("fixture.db", "quoted'file", "接続.sqlite")[index % 3]
            alphabet = ('Alpha "slot"', f'beta{index}', f"保管{index}")
            item = case(order[index % len(order)], rng.choice(finite["temp"]), rng.choice(finite["transaction"]),
                        alias=alias, file_path=ext + str(index), existing=alphabet,
                        labels=(f"00{slot}", f"00{slot+3}", f"0{slot+7}"), depth=rng.randint(1, 6), seed=rng.randint(1, 1000))
            yield f"seed/{seed}/{index}", analysis(item, source(item, optional=index % 2 == 0)), item
            yield f"direct/{seed}-{index}", item, item


def negatives():
    item = case()
    yield analysis(item, "ATTACH (unknown || '') AS extension"), "analysis-rejected", "Column unknown does not exist"
    yield analysis(item, "DETACH missing"), "rejected", "requires a known schema qualifier"
    yield analysis(item, "ATTACH ':memory:' AS TeMP"), "rejected", "cannot select a builtin database"
    wrong = case("detach-bare")
    yield {**wrong, "resolved": "beta"}, "rejected", "must match its resolved database"
    yield {**item, "fileOperand": [["text", "file"], ["text", "other"]]}, "rejected", "exactly one filename operand"
    yield {**item, "keyOperand": [["text", "key"], ["text", "other"]]}, "rejected", "exactly one key operand"


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in AXES)))


def finite_id(measured, domain):
    _reply, native = measured
    choices = [role for role in domain["requests"]
               if native["input"] == source(case(role)) and native["arguments"] == arguments(case(role))]
    require(len(choices) == 1, "sqlite-connection-finite-input", choices)
    before = native["before"]
    coordinate = (choices[0], "warm" if "temp" in before["contents"] else "unopened", before["transaction"])
    return tuple(domain[key].index(value) for key, value in zip(AXES, coordinate))


def forms(measured):
    reply, native = measured
    yield "command/" + reply["command"]
    yield "name/" + reply["nameOperand"][0]
    if native["before"]["attached"] != native["after"]["attached"]:
        yield "effect/" + reply["command"]
    if native["statement"]["evaluationError"] is not None:
        yield "effect/native-refusal"
        if native["statement"]["evaluationError"]["message"].endswith("is locked"):
            yield "effect/selected-btree-locked"
    if reply["keyOperand"] is not None:
        yield "operand/key"
    if reply["name"] == "":
        yield "alias/empty"
    if '"' in (reply["name"] or ""):
        yield "alias/quote"
    if any(ord(ch) > 127 for ch in (reply["name"] or "")):
        yield "alias/unicode"


def required(domain):
    return {"command/attach", "command/detach", "name/text", "name/null", "name/integer", "name/parameter", "effect/attach",
            "effect/detach", "effect/native-refusal", "effect/selected-btree-locked", "operand/key",
            "alias/empty", "alias/quote", "alias/unicode"}


def state(item, schemas, transaction):
    rows, contents = [], {}
    next_slot = 2
    for name, path, marker in schemas:
        if name in ("main", "temp"):
            slot = 0 if name == "main" else 1
        else:
            slot = next_slot
            next_slot += 1
        rows.append((slot, name, path))
        contents[name] = [] if marker is None else result(rows=((item["seed"] + marker,),))["rows"]
    return {"attached": result(rows=rows)["rows"], "contents": contents, "transaction": transaction}


def parameters(item):
    names = {}
    if item["fileOperand"] is not None and item["fileOperand"][0][0] == "parameter":
        label = item["fileOperand"][0][1]
        names[str(int(label))] = "?" + label
    for operand in (item["nameOperand"], None if item["keyOperand"] is None else item["keyOperand"][0]):
        if operand is not None and operand[0] == "parameter":
            names.setdefault(str(int(operand[1])), "?" + operand[1])
    return names


def arguments(item):
    def value(operand):
        kind = operand[0]
        if kind == "parameter":
            return item["bind"].get(str(int(operand[1])))
        if kind == "null":
            return None
        if kind == "integer":
            return int(operand[1])
        if kind == "concat":
            return value(operand[1]) + value(operand[2])
        require(kind == "text", "sqlite-connection-invalid-fixed-operand", operand)
        return operand[1]

    source = item["fileOperand"]
    if source is None:
        return [value(item["nameOperand"])]
    key = item["keyOperand"]
    return [value(source[0]), value(item["nameOperand"]), None if key is None else value(key[0])]


def expected(item):
    before = initial_schemas(item)
    active = before.copy()
    chosen = item["chosen"]
    command = item["command"]
    hit = next((index for index, (name, _, _) in enumerate(before) if ascii_key(name) == ascii_key(chosen)), None)
    error = None
    if command == "attach":
        if hit is not None or ascii_key(chosen) == "temp":
            error = f"database {chosen} is already in use"
        else:
            path = item["nativePath"]
            marker = {item["filePath"]: 19, item["knownPath"]: 7}.get(path)
            active.append((chosen, path, marker))
    elif hit is None:
        error = f"no such database: {chosen}"
    elif ascii_key(chosen) in ("main", "temp"):
        error = f"cannot detach database {chosen}"
    elif item["transaction"] == "locked-alpha" and ascii_key(chosen) == ascii_key(item["existing"][0]):
        error = f"database {chosen} is locked"
    else:
        active.pop(hit)
    names = parameters(item)
    statement = {**result(), "parameterCount": max((int(key) for key in names), default=0), "parameters": names,
                 "evaluationError": None if error is None else {"code": 1, "message": error}}
    return {"before": state(item, before, item["transaction"]), "after": state(item, active, item["transaction"]),
            "settled": state(item, active, "none"), "statement": statement,
            "authorization": [24 if command == "attach" else 25, item["auth"], None, None, None],
            "engine": [f"sqlite_{command}({3 if command == 'attach' else 1})", int(command == "attach")],
            "arguments": arguments(item),
            "fileContents": {item["filePath"]: result(rows=((item["seed"] + 19,),))["rows"],
                             item["knownPath"]: result(rows=((item["seed"] + 7,),))["rows"]}}
