"""Independent generator semantics for the native sequence creation domain."""

from postgres_reindex_cases import qualified, quoted

SCHEMA = "ir_sequence_fixture"
DECOY = "ir_sequence_decoy"
DATABASE = "ir_fixture"
NAME = "ticket"
QUOTED = 'Ticket."棚"'
STATUS = "sequence-creation"
TYPES = {"smallint": (21, -32768, 32767), "integer": (23, -2147483648, 2147483647),
         "bigint": (20, -9223372036854775808, 9223372036854775807)}
MODES = {"permanent": ("", "p"), "unlogged": ("UNLOGGED", "u"),
         "temporary": ("TEMPORARY", "t"), "global": ("GLOBAL TEMPORARY", "t")}
POLICIES = ("default", "explicit", "restart")
NUMBERS = ("increment", "minimum", "maximum", "start", "initial", "cache")
OWNERSHIP = "OWNED BY NONE"
COUNTER_COLUMNS = [["sequence_value", 20]]
INITIAL_COLUMNS = [["last_value", 20], ["log_cnt", 20], ["is_called", 16]]
GLOBAL_WARNING = ["WARNING", "01000", "GLOBAL is deprecated in temporary table creation", None, None]


def trial(tag, kind, direction, policy, persistence, *, coordinate=None, span=14, step=2, cache=3, active=False,
          cycle=None, quoted_name=False, relative=False, guard=False, occupied=None, explicit_none=False):
    local = persistence in ("temporary", "global")
    model = {"release": tag, "coordinate": coordinate, "kind": kind, "policy": policy,
             "persistence": persistence, "target": ["pg_temp" if local else SCHEMA, QUOTED if quoted_name else NAME],
             "guard": guard, "occupied": occupied, "active": active, "explicitNone": explicit_none,
             "relative": relative, "cycle": cycle if cycle is not None else policy == "restart",
             "increment": direction if policy == "default" else direction * step, "cache": 1 if policy == "default" else cache,
             "minimum": None, "maximum": None, "start": None, "initial": None}
    if policy != "default":
        model.update(minimum=-span, maximum=span, start=-direction * (span - 2))
    if policy == "restart":
        model["initial"] = direction * (span - 1)
    return model


def resolved(item):
    _, type_min, type_max = TYPES[item["kind"]]
    positive = item["increment"] > 0
    low = item["minimum"] if item["minimum"] is not None else (1 if positive else type_min)
    high = item["maximum"] if item["maximum"] is not None else (type_max if positive else -1)
    start = item["start"] if item["start"] is not None else (low if positive else high)
    initial = item["initial"] if item["initial"] is not None else start
    return {**item, "minimum": low, "maximum": high, "start": start, "initial": initial}


def direct(item):
    body = {field: None if item[field] is None else str(item[field]) for field in NUMBERS}
    body.update(target=item["target"], ifNotExists=item["guard"], storage=item["kind"].upper(),
                persistence=MODES[item["persistence"]][0], cycle=item["cycle"])
    return {"dialect": "postgres", "release": item["release"], "createSequence": body}


def sql(item, *, canonical=False, change=None):
    model = resolved(item) if canonical else item
    schema, name = model["target"]
    persistence = MODES[model["persistence"]][0] if not model.get("implicitTemp") or canonical else ""
    if model["relative"] and not canonical:
        schema = None
    if model.get("catalogWritten") and not canonical:
        target = quoted(DATABASE) + "." + qualified(schema, name)
    else:
        target = qualified(schema, name)
    prefix = "CREATE " + ((persistence + " ") if persistence else "") + "SEQUENCE "
    prefix += ("IF NOT EXISTS " if model["guard"] else "") + target
    kind = model["kind"].upper()
    words = [prefix, f"AS {kind}"]
    increment = -model["increment"] if change == "reverse" else model["increment"]
    if canonical or increment != 1:
        words.append(f"INCREMENT BY {increment}")
    for key, token in (("minimum", "MINVALUE"), ("maximum", "MAXVALUE"), ("start", "START WITH")):
        if model[key] is not None:
            words.append(f"{token} {model[key]}")
        elif model.get("explicitDefaults") and key != "start":
            words.append(f"NO {token}")
    if change != "restart" and model["initial"] is not None and (not canonical or model["initial"] != model["start"]):
        words.append(f"RESTART WITH {model['initial']}")
    elif model.get("bareRestart") and not canonical:
        words.append("RESTART")
    if canonical or model["cache"] != 1:
        words.append(f"CACHE {model['cache']}")
    words.append("CYCLE" if model["cycle"] and change != "cycle" else "NO CYCLE")
    if model["explicitNone"] and not canonical:
        words.append(OWNERSHIP)
    return " ".join(words)


def analyzed(item):
    return {"dialect": "postgres", "release": item["release"], "sql": item.get("command") or sql(item),
            "currentCatalog": DATABASE, "schemaPath": [SCHEMA, DECOY, "public"],
            "declarations": [f"CREATE SCHEMA {SCHEMA}", f"CREATE SCHEMA {DECOY}",
                             f"CREATE TABLE {SCHEMA}.owner (id integer)"]}


def sequence(item):
    p = resolved(item)
    return {"kind": "S", "persistence": MODES[p["persistence"]][1], "owner": "postgres",
            "storage": TYPES[p["kind"]][0], "increment": p["increment"], "minimum": p["minimum"],
            "maximum": p["maximum"], "start": p["start"], "cache": p["cache"], "cycle": p["cycle"],
            "ownership": [], "tuple": [p["initial"], 0, False], "columns": INITIAL_COLUMNS}


def decoy(item):
    fixture = {**item, "kind": "smallint", "increment": 5, "minimum": 50, "maximum": 70, "start": 61, "initial": 63,
               "cycle": False, "cache": 1, "persistence": "permanent"}
    return sequence(fixture)


def prior(item):
    if item["occupied"] == "sequence":
        return sequence({**item, "kind": "integer", "increment": -3, "minimum": -40, "maximum": -12,
                         "start": -15, "initial": -17, "cycle": False, "cache": 2})
    if item["occupied"] == "table":
        return {"kind": "r", "persistence": MODES[item["persistence"]][1], "owner": "postgres",
                "rows": [[29]], "columns": [["retained", 23]]}
    return None


def generated_values(item):
    p = resolved(item)
    value = p["initial"]
    calls = []
    for _ in range(4):
        if not p["minimum"] <= value <= p["maximum"]:
            if not p["cycle"]:
                calls.append({"sqlState": "2200H", "columns": None, "rows": None})
                continue
            value = p["minimum"] if p["increment"] > 0 else p["maximum"]
        calls.append({"sqlState": "00000", "columns": COUNTER_COLUMNS, "rows": [[value]]})
        value += p["increment"]
    return calls


def expected(item):
    old = prior(item)
    initial = {"selected": old, "decoy": decoy(item)}
    failure = item.get("nativeRefusal")
    notices = [GLOBAL_WARNING] if item["persistence"] == "global" else []
    if old is not None:
        notices.append(["NOTICE", "42P07", 'relation "' + item["target"][1] + '" already exists, skipping', None, None])
    during = {"selected": old or sequence(item), "decoy": decoy(item)} if failure is None else None
    return {"status": STATUS, "sqlState": failure or "00000", "command": "CREATE SEQUENCE" if not failure else None,
            "notices": notices, "transactionAfter": ("failed" if failure else "active") if item["active"] else "idle",
            "before": initial, "during": during,
            "rollback": initial if item["active"] else None,
            "calls": generated_values(item) if not failure and old is None else []}
