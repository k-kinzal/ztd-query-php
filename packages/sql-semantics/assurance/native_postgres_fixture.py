"""Renew the disposable fixture before observers that measure database-wide state."""

import json
import os
import re
import subprocess
import time

from run import TOOL, digest, require, write_json

NAME = "native-postgres-fixture-isolation-v24.json"
CONTRACT = "673e5af22d614fdf1739c7c7055325bbb8e074b9357aa1e1666692ab9ddfdf7d"
STORAGE_NAME = "native-postgres-fixture-storage-v1.json"
STORAGE_CONTRACT = "fee0adc1f3a7a5568346e4b0ba0ce82bf2f552af58121404fdfc05d39a44aa35"
ENV = "ZTD_IR_POSTGRES_FIXTURE_CONTAINER"
IMAGES = {
    "16.6": "postgres@sha256:557fea37a744d5f4c8faab304b0a90858b53ab119735a88c131fd19dab802f36",
    "17.2": "postgres@sha256:3267c505060a0052e5aa6e5175a7b41ab6b04da2f8c4540fc6e98a37210aa2d3",
}
DOMAINS = frozenset({
    "postgres_reindex_schema", "postgres_reindex_partition_table", "postgres_reindex_partition_index",
    "postgres_reindex_database", "postgres_reindex_verbose", "postgres_reindex_tablespace", "postgres_create_schema",
    "postgres_schema_tables", "postgres_schema_sequences", "postgres_schema_views", "postgres_schema_view_dependencies", "postgres_schema_view_replacement", "postgres_primary_key", "postgres_unique_key", "postgres_schema_indexes", "postgres_schema_sequence_grants", "postgres_alter_schema", "postgres_create_sequence", "postgres_drop_sequence", "postgres_sequence_owner", "postgres_rename_sequence", "postgres_move_sequence", "postgres_reconfigure_sequence", "postgres_sequence_logging", "postgres_sequence_authorization", "postgres_sequence_access", "postgres_sequence_default_access",
})
PLACEMENT_DOMAIN = "postgres_reindex_tablespace"
TABLESPACES = ("ir_reindex_left", '置き場."右"')
LOCATION = re.compile(r"/tmp/ztd-ir-reindex-space[.][a-zA-Z0-9]{12}\Z")
SPACE_CENSUS = "SELECT spcname, pg_catalog.pg_tablespace_location(oid) FROM pg_catalog.pg_tablespace WHERE spcname NOT IN ('pg_default','pg_global')"

TIMEOUT = 45
MAINTENANCE = "postgres"
DATABASE = "ir_fixture"
COMMANDS = (f"DROP DATABASE {DATABASE}", f"CREATE DATABASE {DATABASE} TEMPLATE template0")
IDENTITY = "SELECT oid::text FROM pg_catalog.pg_database WHERE datname='ir_fixture' AND datdba=(SELECT oid FROM pg_catalog.pg_roles WHERE rolname='postgres')"
CENSUS = """SELECT current_database(), current_setting('server_version_num'),
  (SELECT oid::text FROM pg_catalog.pg_database WHERE datname=current_database()),
  (SELECT count(*) FROM pg_catalog.pg_namespace WHERE nspname NOT IN ('pg_catalog','pg_toast','information_schema','public')),
  (SELECT count(*) FROM pg_catalog.pg_class WHERE oid>=16384 OR relnamespace='public'::pg_catalog.regnamespace),
  (SELECT count(*) FROM pg_catalog.pg_inherits)"""


def fixed_storage():
    path = TOOL / STORAGE_NAME
    require(digest(path) == STORAGE_CONTRACT, "native-fixture-storage-contract")
    definition = json.loads(path.read_text())
    return {"contract": STORAGE_CONTRACT, "mount": definition["mount"], "filesystem": definition["filesystem"]}


def storage_option():
    (directory, options), = fixed_storage()["mount"].items()
    return f"{directory}:{options}"


def observe_storage(docker, container):
    directory, = fixed_storage()["mount"]
    configuration = docker("inspect", "--format", "{{json .HostConfig.Tmpfs}}", container)
    filesystem = docker("exec", "--user", "postgres", container, "stat", "-f", "-c", "%T", directory)
    return {"contract": STORAGE_CONTRACT, "mount": json.loads(configuration), "filesystem": filesystem}


def verify_storage(record, key):
    require(record == fixed_storage(), "native-fixture-storage-mismatch", {"fixture": key, "observed": record})


def fixed_contract():
    path = TOOL / NAME
    require(digest(path) == CONTRACT, "postgres-fixture-contract-digest")
    definition = json.loads(path.read_text())
    setup = definition["setup"]
    require(set(definition["domains"]) == DOMAINS and set(definition["releases"]) == IMAGES.keys()
            and setup["timeoutSeconds"] == TIMEOUT and list(COMMANDS) == setup["commands"],
            "postgres-fixture-contract-scope")
    require(definition["tablespaceSetup"]["domain"] == PLACEMENT_DOMAIN
            and tuple(definition["tablespaceSetup"]["applicationNames"]) == TABLESPACES,
            "postgres-fixture-tablespace-scope")


def check_container(container, image, port, identity):
    mapping = identity["NetworkSettings"]["Ports"].get("5432/tcp") or []
    require(identity["Id"] == container and identity["Config"]["Image"] == image and identity["State"]["Running"] is True
            and {"POSTGRES_DB=ir_fixture", "POSTGRES_PASSWORD=ir_fixture"} <= set(identity["Config"]["Env"])
            and any(entry["HostPort"] == port for entry in mapping),
            "postgres-fixture-container-authority", {"container": container, "image": image, "port": port})


def verify_report(record, domain, release):
    fixed_contract()
    require(record["contract"] == CONTRACT and record["domain"] == domain and domain in DOMAINS
            and record["release"] == release and record["image"] == IMAGES[release]
            and re.fullmatch(r"[0-9a-f]{64}", record["container"])
            and re.fullmatch(r"[1-9][0-9]{0,4}", record["port"]) and int(record["port"]) <= 65535,
            "postgres-fixture-report-identity", domain)
    commands = record["commands"]
    for action, database, statements in (("before", MAINTENANCE, (IDENTITY,)), ("renew", MAINTENANCE, COMMANDS),
                                         ("after", DATABASE, (CENSUS,))):
        receipt = commands[action]
        require(receipt["exit"] == 0 and receipt["database"] == database and receipt["sql"] == list(statements)
                and receipt["stderr"] == "", "postgres-fixture-command-failed", {"action": action, "receipt": receipt})
    before = commands["before"]["stdout"].strip()
    fields = commands["after"]["stdout"].strip().split("|")
    major, minor = map(int, release.split("."))
    version = str(major * 10000 + minor)
    require(re.fullmatch(r"[1-9][0-9]*", before) and len(fields) == 6 and fields[:2] == [DATABASE, version]
            and re.fullmatch(r"[1-9][0-9]*", fields[2]) and fields[2] != before and fields[3:] == ["0", "0", "0"]
            and commands["renew"]["stdout"].splitlines() == ["DROP DATABASE", "CREATE DATABASE"],
            "postgres-fixture-not-pristine", {"before": before, "after": fields, "domain": domain})
    require(("tablespaceSetup" in record) == (domain == PLACEMENT_DOMAIN), "postgres-fixture-tablespace-authority", domain)
    if domain == PLACEMENT_DOMAIN:
        verify_tablespaces(record["tablespaceSetup"])


def quote_identifier(name):
    return '"' + name.replace('"', '""') + '"'


def create_tablespace(name, location):
    require(LOCATION.fullmatch(location), "postgres-fixture-unexpected-tablespace-path", location)
    return f"CREATE TABLESPACE {quote_identifier(name)} LOCATION '{location}'"


def psql(call, container, database, statements):
    flags = [flag for statement in statements for flag in ("-c", statement)]
    reply = call("exec", "--user", "postgres", container, "psql", "-X", "-A", "-t", "-U", "postgres",
                 "-v", "ON_ERROR_STOP=1", "-d", database, *flags)
    return {"database": database, "sql": list(statements), "stdout": reply.stdout,
            "stderr": reply.stderr, "exit": reply.returncode}


def prepare_tablespaces(call, container):
    created = []
    for name in TABLESPACES:
        result = call("exec", "--user", "postgres", container, "mktemp", "-d", "/tmp/ztd-ir-reindex-space.XXXXXXXXXXXX")
        directory = result.stdout.strip()
        require(result.returncode == 0 and result.stderr == "" and LOCATION.fullmatch(directory),
                "postgres-fixture-tablespace-directory", result.stderr)
        command = psql(call, container, MAINTENANCE, (create_tablespace(name, directory),))
        require(command["exit"] == 0 and command["stderr"] == "", "postgres-fixture-tablespace-creation", command)
        created.append({"name": name, "directory": directory, "command": command})
    return {"created": created, "census": psql(call, container, MAINTENANCE, (SPACE_CENSUS,))}


def verify_tablespaces(setup):
    created = setup["created"]
    require([record["name"] for record in created] == list(TABLESPACES), "postgres-fixture-tablespace-order")
    expected = []
    for record in created:
        name, directory, command = (record[key] for key in ("name", "directory", "command"))
        require(command == {"database": MAINTENANCE, "sql": [create_tablespace(name, directory)],
                            "stdout": "CREATE TABLESPACE\n", "stderr": "", "exit": 0}, "postgres-fixture-tablespace-receipt", name)
        expected.append(f"{name}|{directory}")
    census = setup["census"]
    require(len({record["directory"] for record in created}) == len(TABLESPACES)
            and census["database"] == MAINTENANCE and census["sql"] == [SPACE_CENSUS]
            and census["exit"] == 0 and census["stderr"] == "" and sorted(census["stdout"].splitlines()) == sorted(expected),
            "postgres-fixture-tablespace-census", census)


def renew(domain, release, container, out):
    fixed_contract()
    dsn = os.environ.get("ZTD_IR_POSTGRES_DSN", "")
    endpoint = re.fullmatch(r"pgsql:host=127[.]0[.]0[.]1;port=([1-9][0-9]{0,4});dbname=ir_fixture", dsn)
    require(domain in DOMAINS and release in IMAGES and re.fullmatch(r"[0-9a-f]{64}", container)
            and endpoint is not None and int(endpoint[1]) <= 65535
            and os.environ.get("ZTD_IR_POSTGRES_USER") == "postgres"
            and os.environ.get("ZTD_IR_POSTGRES_PASSWORD") == "ir_fixture",
            "postgres-fixture-invalid-authority", domain)
    deadline = time.monotonic() + TIMEOUT

    def call(*argv):
        remaining = deadline - time.monotonic()
        require(remaining > 0, "postgres-fixture-timeout", domain)
        return subprocess.run(["docker", *argv], capture_output=True, text=True, timeout=remaining, check=False)

    probe = call("container", "inspect", container)
    require(probe.returncode == 0, "postgres-fixture-inspect", probe.stderr)
    identity, = json.loads(probe.stdout)
    port, image = endpoint[1], IMAGES[release]
    check_container(container, image, port, identity)
    record = {"contract": CONTRACT, "domain": domain, "release": release, "image": image, "container": container,
              "port": port, "commands": {}}
    for action, database, statements in (("before", MAINTENANCE, (IDENTITY,)), ("renew", MAINTENANCE, COMMANDS),
                                         ("after", DATABASE, (CENSUS,))):
        command = psql(call, container, database, statements)
        record["commands"][action] = command
        write_json(out, record)
        require(command["exit"] == 0 and not command["stderr"], "postgres-fixture-command-failed", command)
        if action == "before":
            require(re.fullmatch(r"[1-9][0-9]*", command["stdout"].strip()), "postgres-fixture-owner", command["stdout"])
    if domain == PLACEMENT_DOMAIN:
        record["tablespaceSetup"] = prepare_tablespaces(call, container)
        write_json(out, record)
    verify_report(record, domain, release)
    return {"file": out.name, "sha256": digest(out)}


def verify_link(directory, link, domain, release, required):
    if link is None:
        require(not required, "postgres-fixture-report-required", domain)
        return
    require(domain in DOMAINS and link["file"] == "fixture-isolation.json", "postgres-fixture-report-location", domain)
    source = directory / link["file"]
    require(source.is_file() and source.resolve().parent == directory and digest(source) == link["sha256"],
            "postgres-fixture-report-digest", domain)
    verify_report(json.loads(source.read_text()), domain, release)
