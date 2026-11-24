"""Describe native PostgreSQL namespace ownership and guarded creation independently."""

from postgres_reindex_cases import qualified, quoted

CATALOG = "ir_fixture"
STATUS = "schema-creation"
EFFECTIVE = "ir_ns_effective"
SESSION = "ir_ns_session"
OWNER = "ir_ns_owner"
QUOTED_OWNER = 'IR owner."棚"'
DECOY = "ir_ns_unchanged"
SHAPES = ("effective-implicit/explicit", "current-user/explicit", "current-role/explicit",
          "session/explicit", "named/explicit", "current-user/derived", "current-role/derived",
          "session/derived", "named/derived")
TOKENS = {"current-user": "CURRENT_USER", "current-role": "CURRENT_ROLE", "session": "SESSION_USER"}


def trial(tag, shape, occupied, guard, active, *, variant=None, quoted_name=False, base=17, coordinate=None):
    mode, layout = shape.split("/")
    written = (f'IR new {base}."棚"' if quoted_name else f"ir_ns_new_{base + 101}") if layout == "explicit" else None
    named = (variant or (QUOTED_OWNER if quoted_name else OWNER)) if mode == "named" else None
    return {"release": tag, "mode": mode, "name": written, "named": named,
            "occupied": occupied, "guard": guard, "active": active, "base": base, "coordinate": coordinate}


def owner(item):
    return item["named"] or (SESSION if item["mode"] == "session" else EFFECTIVE)


def name(item):
    return item["name"] or owner(item)


def spelling(target, authorization, guard):
    prefix = "CREATE SCHEMA " + ("IF NOT EXISTS " if guard else "")
    return prefix + (quoted(target) if target is not None else "") + (
        (" " if target is not None else "") + "AUTHORIZATION " + authorization if authorization is not None else "")


def original(item):
    authorization = quoted(item["named"]) if item["mode"] == "named" else TOKENS.get(item["mode"])
    return spelling(item["name"], authorization, item["guard"])


def analyzed(item, *, complete=True):
    commands = [f'CREATE ROLE {quoted(OWNER)}', f'CREATE USER {quoted(QUOTED_OWNER)}']
    if item["occupied"]:
        commands.append("CREATE SCHEMA " + quoted(name(item)))
        commands.append(f"CREATE TABLE {qualified(name(item), 'preserved')} (id integer)")
    return {"dialect": "postgres", "release": item["release"], "sql": original(item),
            "declarations": commands, "complete": complete}


def direct(item):
    owner_value = {"named": item["named"]} if item["named"] else {"session": "SESSION_USER" if item["mode"] == "session" else "CURRENT_USER"}
    return {"dialect": "postgres", "release": item["release"],
            "createSchema": {"name": item["name"], "owner": owner_value, "ifNotExists": item["guard"]}}


def shape(item):
    fields = direct(item)["createSchema"]
    return {**fields, "name": item["name"] or item["named"]}


def observed_coordinates(item):
    return sorted({name(item), DECOY, *(value for value in (item["named"], item["name"], EFFECTIVE, SESSION)
                                       if value is not None and value not in ("pg_maintain", "pg_checkpoint"))})


def snapshot(item, created=False):
    occupied = item["occupied"]
    target = name(item)
    namespace = []
    for coordinate in observed_coordinates(item):
        if coordinate == DECOY or coordinate == target and occupied:
            namespace.append([coordinate, OWNER, None])
        elif coordinate == target and created:
            namespace.append([coordinate, owner(item), None])
    return {"namespaces": namespace, "decoy": [item["base"] + 100],
            "targetRows": [item["base"], None] if occupied else None}


def outcome(item):
    occupied, guard, active = (item[key] for key in ("occupied", "guard", "active"))
    missing_stock = item["release"] == "pg-16.6" and item["named"] == "pg_maintain"
    code = "42704" if missing_stock else "42P06" if occupied and not guard else "00000"
    before = snapshot(item)
    during = snapshot(item, created=not occupied) if code == "00000" else None
    notices = []
    if occupied and guard and code == "00000":
        notices.append(["NOTICE", "42P06", f'schema "{name(item)}" already exists, skipping', None, None])
    return {"status": STATUS, "before": before, "during": during,
            "settled": before if active or during is None else during,
            "transactionAfter": ("active" if code == "00000" else "failed") if active else "idle",
            "sqlState": code, "command": "CREATE SCHEMA" if code == "00000" else None, "notices": notices}
