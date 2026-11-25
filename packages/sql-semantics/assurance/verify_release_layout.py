"""Verify exact split-package bytes and exercise installed publication through Composer."""

import argparse
from difflib import unified_diff
import json
import os
from pathlib import Path
import shutil
import subprocess
from tempfile import TemporaryDirectory

from run import IMAGE, PACKAGES, TOOL, Failure, candidate_distribution, digest, require, verify_candidate, write_json
from scope import constructor_scope, declared_constructors, publication_definition

CONTRACT_NAME = "release-layout-v187.json"
CONTRACT = "33ddd3b4c2298929358ee1bbb76f3b93b6b97fc4d2453549673d5c92d3320a91"
DIALECTS = {"MySql": "mysql", "PostgreSql": "postgres", "Sqlite": "sqlite"}


def mysql_varchar_publication():
    ir = "SqlSemantics\\Platform\\MySql\\Ir\\"
    text_slot = ir + "From\\TextColumnReference"
    collated = ir + "Expression\\CollatedText"
    source = " FROM `app`.`native_source` AS `__ztd_source`"
    label, signed = "`__ztd_source`.`label`", "`__ztd_source`.`id`"
    text = f"({label} COLLATE utf8mb4_bin) AS `v`"
    joined_alias = "`__ztd_source_1`"
    local_text, local_signed = f"{joined_alias}.`__ztd_c0`", f"{joined_alias}.`__ztd_c1`"
    definition = f"SELECT {label} AS `__ztd_c0`, {signed} AS `__ztd_c1`{source}"
    joined = (f"SELECT {local_text} AS `message`, {local_signed} AS `local_id` FROM (`app`.`native_source` AS `__ztd_source` "
              f"LEFT JOIN ({definition}) AS {joined_alias} ON ({signed} = {local_signed}))")
    local = ("SELECT `__ztd_local`.`__ztd_c1` AS `item`, `__ztd_local`.`__ztd_c0` AS `item` "
             f"FROM (SELECT {signed} AS `__ztd_c0`, {label} AS `__ztd_c1`{source}) AS `__ztd_local`")
    return {
        "projection": [collated, text_slot, 0, text_slot, 0, "utf8mb4_bin", 8, f"SELECT {text}, {label} AS `v`{source}"],
        "group": ["min", text_slot, 0, False, "max", text_slot, 0, True,
                  f"SELECT min({label}) AS `low`, max(DISTINCT {label}) AS `high`{source}"],
        "write": [["label", "id"], 2, "utf8mb4_bin", False, "int", True, collated, 0, ir + "ColumnReference", 1,
                  f"INSERT INTO `app`.`native_target` (`label`, `id`) SELECT DISTINCT {text}, {signed} AS `n`{source}"],
        "derived": [text_slot, 1, 0, "utf8mb4_bin", 8, True, "int", None, joined],
        "local": [[[1, "item"], [0, "item"]], text_slot, local],
    }



def checked_recipe(candidate, contract, out):
    path, identity = contract["recipe"]["path"], contract["recipe"]["sha256"]
    trusted = TOOL.parents[2] / path
    require(digest(trusted) == identity and digest(candidate / path) == identity, "release-recipe-mismatch")
    text = trusted.read_text()
    section = text.split("      - name: Transform composer.json\n", 1)[1].split("      - name: Remove the monorepo", 1)[0]
    lines = section.split("        run: |\n", 1)[1].splitlines()
    commands = "\n".join(line.removeprefix(" " * 10) for line in lines).strip()
    file = 'FILE="packages/${{ matrix.package.local_path }}/composer.json"'
    version = 'CONSTRAINT="${{ steps.version.outputs.constraint }}"'
    require(commands.count(file) == 1 and commands.count(version) == 1, "unexpected-split-transform")
    script = out / "transform-composer.sh"
    script.write_text('set -euo pipefail\n' + commands.replace(file, 'FILE="$ZTD_SPLIT_FILE"').replace(version, 'CONSTRAINT="$ZTD_SPLIT_CONSTRAINT"') + "\n")
    shutil.copyfile(trusted, out / "split.yml")
    return script


def runtime_bytes(directory):
    files = {}
    for name in ("src", "resources"):
        tree = directory / name
        require(not tree.is_symlink(), "unexpected-package-link", str(tree))
        for path in tree.rglob("*"):
            require(not path.is_symlink(), "unexpected-package-link", str(path))
            if path.is_file():
                files[path.relative_to(directory).as_posix()] = digest(path)
    return files


def verify_bytes(directory, expected):
    current = runtime_bytes(directory)
    require(current == expected, "installed-distribution-mismatch", {
        key: {"expected": expected.get(key), "actual": current.get(key)}
        for key in sorted(expected.keys() | current.keys()) if expected.get(key) != current.get(key)
    })


def command(arguments, cwd, output=None, **variables):
    result = subprocess.run(arguments, cwd=cwd, env={**os.environ, **variables}, text=True, capture_output=True, timeout=120)
    if output is not None:
        output.write_text(result.stdout + result.stderr)
    require(result.returncode == 0, "release-layout-command-failed", {"command": arguments[0], "status": result.returncode})
    return result.stdout


def stage_packages(candidate, stage, manifest, transform, constraint, version):
    expected, repositories = {}, []
    for package in PACKAGES:
        source = candidate / "packages" / package
        target = stage / "packages" / package
        target.mkdir(parents=True)
        for name in ("src", "resources"):
            if (source / name).is_dir():
                shutil.copytree(source / name, target / name)
        metadata = target / "composer.json"
        shutil.copyfile(source / "composer.json", metadata)
        command(["bash", str(transform)], stage, ZTD_SPLIT_FILE=str(metadata), ZTD_SPLIT_CONSTRAINT=constraint)
        original = json.loads((source / "composer.json").read_bytes())
        rewritten = json.loads(metadata.read_bytes())
        requirements = {name: (constraint or value) if name.startswith("k-kinzal/") else value
                        for name, value in original["require"].items()}
        require(rewritten["name"] == original["name"] and rewritten["autoload"] == original["autoload"]
                and rewritten["type"] == "library" and rewritten["require"] == requirements,
                "split-runtime-metadata-mismatch", package)
        prefix = f"packages/{package}/"
        expected[package] = {name.removeprefix(prefix): value for name, value in manifest.items()
                             if name.startswith((prefix + "src/", prefix + "resources/"))}
        verify_bytes(target, expected[package])
        repositories.append({"type": "path", "url": f"packages/{package}",
                             "options": {"symlink": False, "versions": {rewritten["name"]: version}}})
    write_json(stage / "composer.json", {"require": {f"k-kinzal/{package}": version for package in PACKAGES},
                                        "repositories": [*repositories, {"packagist.org": False}]})
    return expected


def runtime_catalog(specification, manifest, inventory):
    declared, _, _ = declared_constructors(DIALECTS)
    interfaces = specification["runtimeRoles"]["interfaces"]
    paths = {name: ("sql-semantics", path, True) for name, path in interfaces.items()}
    for name in declared:
        _, _, platform, *role = name.split("\\")
        paths[name] = ("sql-semantics-" + DIALECTS[platform], "src/" + "/".join(role) + ".php", False)
    classes = {name: value for package in inventory.values() for name, value in package["classes"].items()}
    catalog = {}
    for name, (package, source, interface) in sorted(paths.items()):
        relative = f"packages/{package}/{source}"
        declaration = classes[name]
        require(declaration["file"] == relative and declaration["sha256"] == manifest[relative],
                "installed-catalog-source-mismatch", name)
        kind = {"Stmt_Interface": "interface", "Stmt_Class": "class", "Stmt_Enum": "enum"}[declaration["kind"]]
        require(interface == (kind == "interface"), "installed-catalog-kind-mismatch", name)
        enum = declaration["enum"]
        if kind == "enum":
            require(enum["backing"] == "string" and bool(enum["cases"]), "installed-catalog-enum-mismatch", name)
        catalog[name] = {"path": f"/stage/vendor/k-kinzal/{package}/{source}", "sha256": manifest[relative],
                         "kind": kind, "enum": enum}
    return catalog


def checked_roles(catalog, observations):
    names = {item["name"] for item in observations}
    require(names == catalog.keys() and len(observations) == len(catalog), "incomplete-installed-roles",
            {"missing": sorted(catalog.keys() - names), "unexpected": sorted(names - catalog.keys())})
    measured = {}
    for item in observations:
        name = item["name"]
        expected = catalog[name]
        kind = item["kind"]
        require(kind == expected["kind"] and (kind == "interface" or item["final"])
                and item["path"] == expected["path"] and item["sha256"] == expected["sha256"],
                "installed-role-source-mismatch", {"expected": expected, "observed": item})
        enum = item["enum"]
        if expected["enum"] is None:
            require(enum is None, "installed-enum-route-mismatch", name)
        else:
            require(isinstance(enum, dict) and enum.get("backing") == expected["enum"]["backing"]
                    and enum.get("cases") == expected["enum"]["cases"] and enum.get("unknownRejected") is True
                    and isinstance(enum.get("unknownValue"), str)
                    and enum["unknownValue"] not in {value for _, value in expected["enum"]["cases"]},
                    "installed-enum-route-mismatch", {"role": name, "expected": expected["enum"], "observed": enum})
        measured[name] = {key: item[key] for key in ("kind", "final", "path", "sha256", "enum")}
    return measured


def role_controls(catalog, observations):
    first, second = observations[:2]
    enum_index = next(index for index, item in enumerate(observations) if item["enum"] is not None)
    enum = observations[enum_index]
    lost_case = {**enum, "enum": {**enum["enum"], "cases": enum["enum"]["cases"][1:]}}
    trials = {
        "omitted-role": (observations[1:], first, "incomplete-installed-roles"),
        "wrong-source": ([{**first, "path": second["path"]}, *observations[1:]], first, "installed-role-source-mismatch"),
        "omitted-enum-case": ([*observations[:enum_index], lost_case, *observations[enum_index + 1:]],
                              enum, "installed-enum-route-mismatch"),
    }
    controls = {}
    for name, (altered, target, expected) in trials.items():
        try:
            checked_roles(catalog, altered)
        except Failure as error:
            require(error.signal == expected, "installed-role-control-wrong-signal", name)
            controls[name] = {"role": target["name"], "signal": error.signal, "detail": error.detail}
        else:
            raise Failure("installed-role-control-accepted", name)
    return controls


def probe(stage, evidence, catalog, catalog_path, local):
    script = TOOL / "release_layout_probe.php"
    if local:
        catalog = {role: {**entry, "path": str(stage / Path(entry["path"]).relative_to("/stage"))}
                   for role, entry in catalog.items()}
        installed_catalog = evidence / "local-runtime-catalog.json"
        write_json(installed_catalog, catalog)
        raw = command(["php", str(script), str(stage), str(installed_catalog)], stage, evidence / "probe.log", XDEBUG_MODE="off")
        return verify_probe(raw, catalog)

    arguments = ["docker", "run", "--pull", "never", "--rm", "--network", "none", "--read-only",
                 "--cap-drop", "ALL", "--security-opt", "no-new-privileges", "--user", "65534:65534",
                 "--pids-limit", "64", "--memory", "256m", "-v", f"{stage / 'vendor'}:/stage/vendor:ro", "-v", f"{script}:/probe.php:ro",
                 "-v", f"{catalog_path}:/roles.json:ro",
                 IMAGE, "php", "/probe.php"]
    raw = command(arguments, stage, evidence / "probe.log")

    return verify_probe(raw, catalog)


def verify_probe(raw, catalog):
    frames = [json.loads(line) for line in raw.splitlines()]
    results = [item for item in frames if item["probe"] == "publication"]
    role_observations = [item for item in frames if item["probe"] == "role"]
    require(len(frames) == len(results) + len(role_observations), "unexpected-installed-probe")
    pairs = {(item["platform"], item["role"]) for item in results}
    publication = publication_definition()
    single = publication["single"]
    expected = {(platform, role.removeprefix("Ir\\"))
                for platform in publication["dialects"]
                for role in (single["result"], *single["additionalDialectResults"][platform])}
    require(pairs == expected and len(results) == len(expected), "incomplete-installed-publication")
    for item in results:
        name = "SqlSemantics\\Platform\\" + item["platform"] + "\\Ir\\" + item["role"]
        path = catalog[name]["path"]
        require(item["path"] == path and isinstance(item["sql"], str) and item["sql"] and item["profile"],
                "unexpected-installed-publication", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Select"):
            require(item.get("joinedLocal") == [True, 0, 1, 1], "installed-joined-local-source-mismatch", item)
            require(item.get("coalesce") == [2, "007", 'SELECT COALESCE(NULL, ?007) AS "chosen"'],
                    "installed-coalesce-mismatch", item)
            require(item.get("viewInput") == ["main", "removable", "view", ["id"], 0],
                    "installed-external-view-input-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Rows\\With\\WithProjection"):
            require(item.get("withCoordinates") == [0, 0, 1, 0, 1] and item["sql"] ==
                    'WITH `__ztd_cte_0` AS (SELECT 42 AS `__ztd_c0`), '
                    '`__ztd_cte_1` AS (SELECT `__ztd_cte_0`.`__ztd_c0` AS `__ztd_c0` FROM `__ztd_cte_0`) '
                    'SELECT `__ztd_cte_1`.`__ztd_c0` AS `n` FROM `__ztd_cte_1`', "installed-mysql-with-coordinates-mismatch", item)
            require(item.get("withAvailability") == [
                [release, "WITH requires MySQL 8.0 or later.", "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\AnalysisException"]
                for release in ("mysql-5.6.51", "mysql-5.7.44")
            ], "installed-mysql-with-availability-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Rows\\With\\WithProjection"):
            local_values = ('WITH "__ztd_cte_0" AS MATERIALIZED (SELECT "__ztd_c0", "__ztd_c1" FROM '
                            '(VALUES (CAST(5 AS pg_catalog.int4), TRUE), (NULL, FALSE)) AS "__ztd_values" '
                            '("__ztd_c0", "__ztd_c1")) SELECT "__ztd_cte_0"."__ztd_c1" AS "flag", '
                            '"__ztd_cte_0"."__ztd_c0" AS "id" FROM "__ztd_cte_0"')
            require(item.get("withValues") == [2, "int4", "bool", 0, [1, 0], local_values],
                    "installed-postgres-local-values-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Rows\\Compound"):
            set_sql = ('VALUES (CAST(5 AS pg_catalog.int4)), (CAST(5 AS pg_catalog.int4)) EXCEPT ALL '
                       '(SELECT CAST(5 AS pg_catalog.int4) AS "other" UNION ALL VALUES (CAST(4000000000 AS pg_catalog.int8)))')
            require(item.get("valuesCompound") == ["EXCEPT", True, "int4", "int8", set_sql],
                    "installed-postgres-values-compound-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Rows\\Values\\ValuesQuery"):
            require(item.get("valuesHeading") == ["bool", "int8", 2] and item["sql"] ==
                    "VALUES (FALSE, NULL), (TRUE, CAST(4000000000 AS pg_catalog.int8))",
                    "installed-postgres-values-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\View\\CreateView"):
            expected_sql = ('CREATE OR REPLACE VIEW "public"."ready_rows" ("n") WITH (security_invoker=false) '
                            'AS SELECT "__ztd_source"."id" AS "id" FROM "public"."accounts" AS "__ztd_source" '
                            'WHERE ("__ztd_source"."id" OPERATOR(pg_catalog.>) CAST(0 AS pg_catalog.int4)) WITH LOCAL CHECK OPTION')
            require(item.get("postgresCreateView") == ["public", "ready_rows", ["n"], True, None, False, "LOCAL",
                                                     "public", "accounts", 0] and item["sql"] == expected_sql,
                    "installed-postgres-create-view-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Query\\CreateTableAs"):
            require(item.get("postgresCreateTableAs") == ["public", "copy_probe", ["n"], "int4", True, False, False]
                    and item["sql"] == ('CREATE UNLOGGED TABLE "public"."copy_probe" ("n")'
                                        ' USING heap AS VALUES (CAST(6 AS pg_catalog.int4)) WITH NO DATA'),
                    "installed-postgres-create-table-as-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\View\\CreateMaterializedView"):
            require(item.get("postgresMaterializedView") == ["public", "materialized_probe", ["n"], "int4", False, False]
                    and item["sql"] == ('CREATE MATERIALIZED VIEW "public"."materialized_probe" ("n")'
                                        ' USING heap AS VALUES (CAST(6 AS pg_catalog.int4)) WITH NO DATA'),
                    "installed-postgres-materialized-view-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\View\\RefreshMaterializedView"):
            require(item.get("postgresRefreshMaterializedView") == ["public", "snapshot", False]
                    and item["sql"] == 'REFRESH MATERIALIZED VIEW "public"."snapshot" WITH NO DATA',
                    "installed-postgres-refresh-materialized-view-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Index\\Btree\\CreateIndex"):
            bound_only = ('CREATE INDEX "bound_only" ON "public"."bound_fields" USING btree '
                          '("amount" ASC NULLS LAST) WHERE ("flags" IS TRUE)')
            require(item.get("postgresIndexOperandBindings") == [
                True, [[1, "amount", "int4"], [2, "flags", "bool"]], bound_only, bound_only,
            ], "installed-postgres-index-operand-bindings", item)
            require(item.get("postgresAutomaticIndexNames") == [
                "naming_id_enabled_idx1", True, 'CREATE INDEX "naming_id_enabled_idx1" ON "public"."naming" '
                'USING btree ("id" ASC NULLS LAST) INCLUDE ("enabled")', "t_name_idx1", "t_name_idx2",
            ], "installed-postgres-automatic-index-names", item)
            require(item.get("postgresAnonymousCatalog") == [
                'DROP INDEX "named_prior"."t_id_idx1" RESTRICT', "named_prior", "t_id_idx2", "t", "t_id_idx3",
            ], "installed-postgres-anonymous-catalog", item)
            require(item.get("postgresIndexTextKeys") == [
                True, "C", "text", 'CREATE INDEX "codes" ON "public"."collated_index_owner" USING btree '
                '("code" COLLATE pg_catalog."C" pg_catalog.text_ops ASC NULLS LAST) INCLUDE ("payload")',
            ], "installed-postgres-text-index-keys", item)
            require(item.get("postgresDeclaredIndexCollation") == [True, [1, "C"], "copy_fields", "text"],
                    "installed-postgres-declared-index-collation", item)
            emitted = ('CREATE UNIQUE INDEX "new index" ON "public"."ledger" USING btree '
                       '("wide" DESC NULLS LAST, "value" ASC NULLS FIRST) INCLUDE ("ready", "ready") NULLS NOT DISTINCT')
            require(item.get("postgresCreateBtree") == ["public", ["int4", "int8", "bool"],
                                                       [[1, "DESC NULLS LAST"], [0, "ASC NULLS FIRST"]], [2, 2],
                                                       "vacant", emitted, "occupied", True]
                    and item["sql"] == emitted, "installed-postgres-create-btree-mismatch", item)
            partial = ('CREATE INDEX "partial probe" ON "public"."ledger" USING btree ("value" ASC NULLS LAST)'
                       ' WHERE ("ready" IS NOT NULL)')
            require(item.get("postgresPartialBtree") == [2, True, partial, partial],
                    "installed-postgres-partial-btree-mismatch", item)
            formula = ('CREATE INDEX "composite" ON "public"."ledger" USING btree ("wide" ASC NULLS LAST)'
                       ' WHERE (NOT (("value" IS NULL) OR ("ready" IS NOT NULL)))')
            require(item.get("postgresBooleanIndexPredicate") == ["NOT", "OR", 0, 2, formula, formula, True],
                    "installed-postgres-boolean-index-predicate-mismatch", item)
            truth = ('CREATE INDEX "truth probe" ON "public"."ledger" USING btree ("wide" ASC NULLS LAST)'
                     ' WHERE ("ready" IS NOT UNKNOWN)')
            require(item.get("postgresIndexTruthTest") == [2, "IS NOT UNKNOWN", truth, truth, True],
                    "installed-postgres-index-truth-test-mismatch", item)
            field = ('CREATE INDEX "field probe" ON "public"."ledger" USING btree ("wide" ASC NULLS LAST)'
                     ' WHERE (NOT ("ready"))')
            require(item.get("postgresIndexBooleanValue") == ["NOT", 2, field, field, True],
                    "installed-postgres-index-boolean-value-mismatch", item)
        if item["platform"] == "PostgreSql" and item["role"] in {
            "Schema\\Index\\ReindexIndex", "Schema\\Index\\ReindexTable",
            "Schema\\Index\\Partition\\ReindexPartitionTable", "Schema\\Index\\Scope\\ReindexSchema",
            "Schema\\Index\\Scope\\ReindexDatabase", "Schema\\Index\\Scope\\ReindexSystemCatalogs",
        }:
            ordinary = item["sql"]
            require(ordinary.startswith("REINDEX ") and item.get("postgresReindexVerbose") == [
                True, True, "REINDEX (VERBOSE) " + ordinary.removeprefix("REINDEX "), True, False, ordinary,
            ], "installed-postgres-rebuild-reporting", item)
            placed = 'REINDEX (VERBOSE, TABLESPACE "cold ""archive""") ' + ordinary.removeprefix("REINDEX ")
            require(item.get("postgresReindexPlacement") == [True, 'cold "archive"', True, placed, placed, True],
                    "installed-postgres-rebuild-placement", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Namespace\\RenameSchema"):
            emitted = 'ALTER SCHEMA "Tax.Reg" RENAME TO "Archive.2025"'
            require(item.get("postgresRenameSchema") == ["Tax.Reg", "Archive.2025", emitted, True]
                    and item["sql"] == emitted, "installed-postgres-rename-schema-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Namespace\\ChangeSchemaOwner"):
            emitted = 'ALTER SCHEMA "Tax.Reg" OWNER TO "Tax Owner"'
            require(item.get("postgresChangeSchemaOwner") == [
                "Tax.Reg", "Tax Owner", emitted, "Session", 'ALTER SCHEMA "Tax.Reg" OWNER TO SESSION_USER', True,
            ] and item["sql"] == emitted, "installed-postgres-schema-owner-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Sequence\\Alter\\SetSequenceLogging"):
            emitted = 'ALTER SEQUENCE "public"."ticket" SET LOGGED, SET UNLOGGED'
            guarded = 'ALTER SEQUENCE IF EXISTS "unused" SET LOGGED'
            modes = ["LOGGED", "UNLOGGED"]
            require(item.get("postgresSequenceLogging") == ["public", "ticket", modes, modes, emitted, True, True, [], guarded, True]
                    and item["sql"] == emitted, "installed-postgres-sequence-logging-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Sequence\\Authorization\\AlterSequenceAuthorization"):
            actions = ["SET LOGGED", 'OWNER TO "Tax Owner"', "OWNER TO SESSION_USER", "SET UNLOGGED"]
            emitted = 'ALTER SEQUENCE "public"."ticket" ' + ", ".join(actions)
            guarded = 'ALTER SEQUENCE IF EXISTS "unused" OWNER TO CURRENT_USER'
            require(item.get("postgresSequenceAuthorization") == ["public", "ticket", actions, emitted, True, True, [], guarded, True]
                    and item["sql"] == emitted, "installed-postgres-sequence-authorization-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Sequence\\Access\\GrantSequenceAccess"):
            target = 'SEQUENCE "pg_temp"."ticket", "public"."ticket"'
            emitted = f'GRANT UPDATE, USAGE ON {target} TO "PUBLIC", SESSION_USER WITH GRANT OPTION GRANTED BY CURRENT_USER'
            legacy = 'GRANT RULE ON SEQUENCE "public"."ticket" TO PUBLIC WITH GRANT OPTION'
            require(item.get("postgresSequenceAccess") == [target, ["UPDATE", "USAGE"], '"PUBLIC", SESSION_USER',
                                                          " GRANTED BY CURRENT_USER", emitted, True, True, legacy]
                    and item["sql"] == emitted, "installed-postgres-sequence-grant-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Sequence\\Access\\RevokeSequenceAccess"):
            target = 'ALL SEQUENCES IN SCHEMA "Audit.Log", "public"'
            emitted = f'REVOKE GRANT OPTION FOR SELECT ON {target} FROM PUBLIC, "PUBLIC" GRANTED BY SESSION_USER CASCADE'
            require(item.get("postgresSequenceAccess") == [target, ["SELECT"], 'PUBLIC, "PUBLIC"', " GRANTED BY SESSION_USER",
                                                          emitted, True, True, None]
                    and item["sql"] == emitted, "installed-postgres-sequence-revoke-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Sequence\\Defaults\\GrantSequenceDefaults"):
            scope = 'ALTER DEFAULT PRIVILEGES FOR ROLE "Owner Role", CURRENT_USER IN SCHEMA "Policy.Archive"'
            rights, recipients = 'USAGE, UPDATE ON SEQUENCES', '"Reader Role", SESSION_USER'
            emitted = f'{scope} GRANT {rights} TO {recipients} WITH GRANT OPTION'
            require(item.get("postgresSequenceDefaultAccess") == [scope, rights, recipients, emitted, True, True, True]
                    and item["sql"] == emitted, "installed-postgres-sequence-default-grant-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Sequence\\Defaults\\RevokeSequenceDefaults"):
            scope, rights, recipients = 'ALTER DEFAULT PRIVILEGES', 'ALL ON SEQUENCES', 'PUBLIC, CURRENT_USER'
            emitted = f'{scope} REVOKE GRANT OPTION FOR {rights} FROM {recipients} CASCADE'
            require(item.get("postgresSequenceDefaultAccess") == [scope, rights, recipients, emitted, True, True, True]
                    and item["sql"] == emitted, "installed-postgres-sequence-default-revoke-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Sequence\\Alter\\ReconfigureSequence"):
            emitted = 'ALTER SEQUENCE "public"."ticket" START WITH 5 RESTART CYCLE OWNED BY NONE'
            guarded = 'ALTER SEQUENCE IF EXISTS "unused" RESTART'
            require(item.get("postgresReconfigureSequence") == ["public", "ticket", "5", "native-default", "remove",
                                                                None, emitted, True, True, None, guarded, True]
                    and item["sql"] == emitted, "installed-postgres-reconfigure-sequence-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Sequence\\Alter\\MoveSequence"):
            emitted = 'ALTER SEQUENCE "public"."ticket" SET SCHEMA "Archive.棚"'
            guarded = 'ALTER SEQUENCE IF EXISTS "missing"."ticket" SET SCHEMA "pg_catalog"'
            require(item.get("postgresMoveSequence") == ["public", "ticket", "existing", "Archive.棚", emitted, True, True, None, guarded, True]
                    and item["sql"] == emitted, "installed-postgres-move-sequence-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Sequence\\Alter\\RenameSequence"):
            emitted = 'ALTER SEQUENCE "pg_temp"."ticket" RENAME TO "Next.Ticket"'
            require(item.get("postgresRenameSequence") == ["pg_temp", "ticket", "existing", "Next.Ticket", True, emitted, True]
                    and item["sql"] == emitted, "installed-postgres-rename-sequence-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Sequence\\DropSequence"):
            emitted = 'DROP SEQUENCE IF EXISTS "pg_temp"."ticket", "pg_temp"."ticket", "public"."absent", "unknown" CASCADE'
            targets = [["pg_temp", "ticket", "existing"], ["pg_temp", "ticket", "existing"],
                       ["public", "absent", "missing-sequence"], [None, "unknown", "missing-sequence"]]
            require(item.get("postgresDropSequence") == [targets, True, emitted, True] and item["sql"] == emitted,
                    "installed-postgres-drop-sequence-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Sequence\\CreateSequence"):
            emitted = ('CREATE UNLOGGED SEQUENCE IF NOT EXISTS "public"."Ticket.棚" AS SMALLINT INCREMENT BY 2 '
                       'MINVALUE 1 MAXVALUE 32767 START WITH 3 RESTART WITH 10 CACHE 2 CYCLE')
            require(item.get("postgresCreateSequence") == [
                "public", "Ticket.棚", "Unlogged", "SMALLINT", "3", "10", True, True, emitted, True,
            ] and item["sql"] == emitted, "installed-postgres-create-sequence-mismatch", item)
            owner_sql = ('CREATE SEQUENCE "public"."owner_ticket" AS SMALLINT INCREMENT BY 1 MINVALUE 1'
                         ' MAXVALUE 32767 START WITH 1 CACHE 1 NO CYCLE OWNED BY "public"."ledger"."tag"')
            require(item.get("postgresSequenceOwner") == [
                "public", "ledger", "tag", "table", True, owner_sql, True,
            ], "installed-postgres-sequence-owner-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Namespace\\CreateSchema"):
            sequence_first = ('CREATE SCHEMA "Counter Batch" AUTHORIZATION CURRENT_USER'
                              ' CREATE SEQUENCE "Counter Batch"."ticket" AS SMALLINT INCREMENT BY 1 MINVALUE 1'
                              ' MAXVALUE 32767 START WITH 3 RESTART WITH 8 CACHE 1 NO CYCLE'
                              ' CREATE TABLE IF NOT EXISTS "Counter Batch"."ticket" ("n" INTEGER NULL) USING heap')
            require(item.get("postgresSchemaSequences") == [
                "Counter Batch", "ticket", "3", "8", True, sequence_first, sequence_first, True,
            ], "installed-postgres-schema-sequences-mismatch", item)
            emitted = 'CREATE SCHEMA IF NOT EXISTS "Tax.Reg" AUTHORIZATION "Tax Owner"'
            require(item.get("postgresCreateSchema") == [
                "Tax.Reg", "Tax Owner", True, emitted, [None, "Effective", "CREATE SCHEMA AUTHORIZATION CURRENT_USER"],
                [None, "Session", "CREATE SCHEMA AUTHORIZATION SESSION_USER"],
            ] and item["sql"] == emitted, "installed-postgres-create-schema-mismatch", item)
            composed = ('CREATE SCHEMA "Batch" AUTHORIZATION "Tax Owner"'
                        ' CREATE TABLE "Batch"."child" ("n" INTEGER NULL) USING heap'
                        ' CREATE TABLE IF NOT EXISTS "Batch"."child" ("wide" BIGINT NULL) USING heap')
            require(item.get("postgresSchemaTables") == [
                [["Batch", "child", "n", "INTEGER", False], ["Batch", "child", "wide", "BIGINT", True]],
                composed, composed, True,
            ], "installed-postgres-schema-tables-mismatch", item)
            view = ('CREATE SCHEMA "View Batch" AUTHORIZATION CURRENT_USER'
                    ' CREATE TABLE "View Batch"."child" ("n" INTEGER NULL) USING heap'
                    ' CREATE VIEW "View Batch"."feed" ("Number") AS'
                    ' SELECT "__ztd_source"."n" AS "n" FROM "View Batch"."child" AS "__ztd_source"')
            require(item.get("postgresSchemaViews") == [
                "View Batch", "feed", "Number", "View Batch", "child", "n", "int4", True, view, view, True, True,
            ], "installed-postgres-schema-views-mismatch", item)
            indexes = ('CREATE SCHEMA "Index Batch" AUTHORIZATION CURRENT_USER'
                       ' CREATE TABLE "Index Batch"."ledger" ("id" INTEGER NULL, "amount" BIGINT NULL, "enabled" pg_catalog.bool NULL) USING heap'
                       ' CREATE VIEW "Index Batch"."seen" ("?column?") AS SELECT CAST(1 AS pg_catalog.int4) AS "?column?"'
                       ' CREATE UNIQUE INDEX "picked" ON "Index Batch"."ledger" USING btree ("amount" DESC NULLS FIRST)'
                       ' NULLS NOT DISTINCT WHERE ("enabled")'
                       ' CREATE INDEX IF NOT EXISTS "seen" ON "Index Batch"."ledger" USING btree ("amount" ASC NULLS LAST)')
            require(item.get("postgresSchemaIndexes") == [
                "Index Batch", "ledger", "picked", 1, "DESC NULLS FIRST", "int8", True, "vacant", "occupied",
                indexes, indexes, True,
            ], "installed-postgres-schema-indexes-mismatch", item)
            grants = ('CREATE SCHEMA "Policy Batch" AUTHORIZATION "Tax Owner"'
                      ' CREATE SEQUENCE "Policy Batch"."feed" AS BIGINT INCREMENT BY 1 MINVALUE 1'
                      ' MAXVALUE 9223372036854775807 START WITH 1 CACHE 1 NO CYCLE'
                      ' GRANT USAGE ON SEQUENCE "Policy Batch"."feed" TO SESSION_USER, "PUBLIC"'
                      ' WITH GRANT OPTION GRANTED BY CURRENT_USER')
            require(item.get("postgresSchemaSequenceGrants") == [
                "Tax Owner", "Policy Batch", "feed", True, "SESSION_USER", '"PUBLIC"', "CURRENT_USER", True,
                grants, grants, True,
            ], "installed-postgres-schema-sequence-grants-mismatch", item)
            dependencies = ('CREATE SCHEMA "Chain Batch" AUTHORIZATION CURRENT_USER'
                             ' CREATE VIEW "Chain Batch"."source" ("n", "mystery") AS'
                             ' SELECT CAST(18 AS pg_catalog.int4) AS "?column?", NULL AS "?column?"'
                             ' CREATE VIEW "Chain Batch"."leaf" ("result") AS'
                             ' SELECT "__ztd_source"."mystery" AS "mystery" FROM "Chain Batch"."source" AS "__ztd_source"')
            external = ('CREATE VIEW "public"."mirror" ("note") AS'
                        ' SELECT "__ztd_source"."note" AS "note" FROM "public"."upstream" AS "__ztd_source"')
            require(item.get("postgresSchemaViewDependencies") == [
                "view", "Chain Batch", "source", "mystery", "text", "result", dependencies, dependencies, True, "view", external,
            ], "installed-postgres-schema-view-dependencies-mismatch", item)
            replacement = (
                'CREATE SCHEMA "Replacement Batch" AUTHORIZATION CURRENT_USER'
                ' CREATE VIEW "Replacement Batch"."feed" ("n", "c") AS'
                " SELECT CAST(1 AS pg_catalog.int4) AS \"?column?\", CAST('old' AS pg_catalog.text) AS \"?column?\""
                ' CREATE VIEW "Replacement Batch"."prev" ("n") AS'
                ' SELECT "__ztd_source"."n" AS "n" FROM "Replacement Batch"."feed" AS "__ztd_source"'
                ' CREATE OR REPLACE VIEW "Replacement Batch"."feed" ("n", "c", "enabled") AS'
                " SELECT CAST(2 AS pg_catalog.int4) AS \"?column?\", CAST('changed' AS pg_catalog.text) AS \"?column?\", TRUE AS \"?column?\""
                ' CREATE VIEW "Replacement Batch"."next" ("enabled") AS'
                ' SELECT "__ztd_source"."enabled" AS "enabled" FROM "Replacement Batch"."feed" AS "__ztd_source"')
            require(item.get("postgresSchemaViewReplacement") == [
                False, True, ["n", "c", "enabled"], "enabled", "bool", replacement, replacement, True,
            ], "installed-postgres-schema-view-replacement-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Namespace\\DropSchema"):
            emitted = 'DROP SCHEMA IF EXISTS "reporting.data", "Absent", "Absent" CASCADE'
            require(item.get("postgresDropSchema") == [
                [["reporting.data", True], ["Absent", False], ["Absent", False]], True, True, emitted,
                [emitted, 'DROP SCHEMA "decoy" RESTRICT'], True,
            ] and item["sql"] == emitted, "installed-postgres-drop-schema-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Index\\ReindexIndex"):
            rebuilt = 'REINDEX INDEX CONCURRENTLY "pg_temp"."removal"'
            require(item.get("postgresReindexIndex") == [
                "pg_temp", "removal", "keyed", "concurrently", rebuilt, rebuilt,
                'REINDEX INDEX "public"."removal"', 'REINDEX INDEX "pg_temp"."removal"',
            ] and item["sql"] == rebuilt, "installed-postgres-reindex-index-mismatch", item)
            constraint = 'REINDEX INDEX "public"."saved_key"'
            require(item.get("postgresReindexConstraintIndex") == [
                True, "indexed_items", constraint, constraint, True,
            ], "installed-postgres-reindex-constraint-index-mismatch", item)
            physical = 'REINDEX INDEX CONCURRENTLY "regional"."physical"'
            require(item.get("postgresReindexPartitionIndex") == [
                "branch", "groups", 'REINDEX INDEX "public"."branch_idx"',
                "leaf", "child", physical, physical,
            ], "installed-postgres-reindex-partition-index-mismatch", item)
            exclusion = 'REINDEX INDEX "public"."avoid_overlap"'
            temporary = 'REINDEX INDEX CONCURRENTLY "pg_temp"."avoid_overlap"'
            require(item.get("postgresReindexExclusionIndex") == [
                True, "booked", exclusion, exclusion, temporary, True,
            ], "installed-postgres-reindex-exclusion-index-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Index\\ReindexTable"):
            emitted = 'REINDEX TABLE CONCURRENTLY "public"."snapshot"'
            require(item.get("postgresReindexTable") == [
                "public", "snapshot", "materialized-view", "concurrently", emitted,
                "base-table", 'REINDEX TABLE "pg_temp"."orders"', True,
            ] and item["sql"] == emitted, "installed-postgres-reindex-table-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Index\\Scope\\ReindexSchema"):
            emitted = 'REINDEX SCHEMA CONCURRENTLY "reporting.data"'
            require(item.get("postgresReindexSchema") == ["reporting.data", "concurrently", emitted, emitted]
                    and item["sql"] == emitted, "installed-postgres-reindex-schema-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Index\\Scope\\ReindexDatabase"):
            emitted = 'REINDEX DATABASE CONCURRENTLY "billing.data"'
            require(item.get("postgresReindexDatabase") == ["billing.data", "concurrently", emitted]
                    and item["sql"] == emitted, "installed-postgres-reindex-database-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Index\\Scope\\ReindexSystemCatalogs"):
            emitted = 'REINDEX SYSTEM "billing.data"'
            require(item.get("postgresReindexSystemCatalogs") == ["billing.data", emitted]
                    and item["sql"] == emitted, "installed-postgres-reindex-system-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Index\\Partition\\ReindexPartitionTable"):
            emitted = 'REINDEX TABLE CONCURRENTLY "public"."partitioned_items"'
            require(item.get("postgresReindexPartitionTable") == [
                "branch", "public", "partitioned_items", emitted, "leaf", "regional", "child", 'REINDEX TABLE "regional"."child"',
            ] and item["sql"] == emitted, "installed-postgres-partition-reindex-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Index\\DropIndex"):
            emitted = 'DROP INDEX IF EXISTS "pg_temp"."removal", "absent_namespace"."idx" CASCADE'
            require(item.get("postgresDropIndex") == ["pg_temp", "removal", "keyed", "absent_namespace", True, emitted,
                                                     "concurrently", 'DROP INDEX CONCURRENTLY "public"."removal" RESTRICT', True]
                    and item["sql"] == emitted, "installed-postgres-drop-index-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\View\\DropView"):
            require(item.get("dropView") == ["MATERIALIZED VIEW", "public", "snapshot", False, True, "VIEW",
                                             'DROP VIEW IF EXISTS "public"."removable" RESTRICT']
                    and item["sql"] == 'DROP MATERIALIZED VIEW "public"."snapshot" CASCADE',
                    "installed-postgres-drop-view-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\View\\DropView"):
            require(item.get("dropView") == ["app", "absent_view", True] and item["sql"] == 'DROP VIEW IF EXISTS `app`.`absent_view`',
                    "installed-mysql-drop-view-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\View\\CreateView"):
            require(item.get("createView") == ["require-new", "TEMPTABLE", "INVOKER", ["value"],
                                               'CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `app`.`filtered` (`value`) '
                                               'AS SELECT 42 AS `__ztd_c0` FROM DUAL WHERE FALSE']
                    and item["sql"] == 'CREATE ALGORITHM=TEMPTABLE SQL SECURITY INVOKER VIEW `app`.`answer` (`value`) AS SELECT 42 AS `__ztd_c0`',
                    "installed-mysql-create-view-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Select"):
            require(item.get("signedCast") == "42" and item.get("unsignedCast") == "-1", "installed-integral-cast-mismatch", item)
            require(item.get("mysqlTextLiteral") == ["it's \0𐐷", "sample", "SELECT 'it''s \\0𐐷' AS `sample`",
                                                     "utf8mb4_general_ci", "utf8mb4_0900_ai_ci"],
                    "installed-mysql-text-literal-mismatch", item)
            require(json.dumps(item.get("mysqlVarcharPublication"), sort_keys=True) == json.dumps(mysql_varchar_publication(), sort_keys=True),
                    "installed-mysql-varchar-publication-mismatch", item)
            require(item.get("mysqlExternalViews") == [
                "view", "app", "external_rows", ["key_value", "label_value"], 8, "utf8mb4_bin", True,
                'SELECT `__ztd_source`.`key_value` AS `key_value`, `__ztd_source`.`label_value` AS `label_value` '
                'FROM `app`.`external_rows` AS `__ztd_source`',
                "A DELETE target must be a base table.",
            ], "installed-mysql-external-view-mismatch", item)
            mysql_ir = r"SqlSemantics\Platform\MySql\Ir" + chr(92)
            require(item.get("mysqlHaving") == [
                mysql_ir + r"Aggregation\GroupedComparison", True, "sum",
                mysql_ir + r"Predicate\RangeIntegerLiteral", "4294967292", mysql_ir + "ColumnReference", 0,
                'SELECT `__ztd_source`.`id` AS `id`, sum(`__ztd_source`.`amount`) AS `total` '
                'FROM `app`.`having_rows` AS `__ztd_source` GROUP BY `__ztd_source`.`id` HAVING (sum(`__ztd_source`.`amount`) > 4294967292)',
                'SELECT count(*) AS `id` FROM `app`.`having_rows` AS `__ztd_source` GROUP BY `__ztd_source`.`id` HAVING (`__ztd_source`.`id` > 1)',
            ], "installed-mysql-having-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Mutation\\Delete"):
            require(item.get("booleanReturning") == [
                "SqlSemantics\\Platform\\PostgreSql\\Ir\\From\\BooleanColumnReference", True, True,
                ["flag", "id"], 'DELETE FROM "public"."flags" WHERE "enabled" RETURNING "enabled" AS "flag", "id" AS "id"',
            ], "installed-postgres-boolean-returning-mismatch", item)
            boolean_slot = "SqlSemantics\\Platform\\PostgreSql\\Ir\\From\\BooleanColumnReference"
            require(item.get("booleanWrites") == [
                [[0, "bool"], [1, "bool"]], True, None,
                'INSERT INTO "public"."flags" ("enabled", "fallback") VALUES (TRUE, NULL)',
                "bool", True,
                'INSERT INTO "public"."flags" ("fallback") SELECT DISTINCT CAST(NULL AS pg_catalog.bool) AS "typed"',
                [[boolean_slot, 0, 1], [boolean_slot, 1, 0]],
                'UPDATE "public"."flags" AS "__ztd_source" SET "enabled" = "__ztd_source"."fallback", '
                '"fallback" = "__ztd_source"."enabled"',
            ], "installed-postgres-boolean-writes-mismatch", item)
            require(item.get("booleanInput") == [
                "SqlSemantics\\Platform\\PostgreSql\\Ir\\BooleanLiteral", False, "bool", True,
                "A query INSERT Boolean target requires a Boolean result or NULL resolved before DISTINCT.",
                'INSERT INTO "public"."flags" ("enabled") SELECT FALSE AS "value"',
                'INSERT INTO "public"."flags" ("enabled") SELECT DISTINCT CAST(TRUE AS pg_catalog.bool) AS "value"',
            ], "installed-postgres-boolean-input-mismatch", item)
            wide = "SqlSemantics\\Platform\\PostgreSql\\Ir\\From\\WideIntegerColumnReference"
            require(item.get("signedWrites") == [
                "SqlSemantics\\Platform\\PostgreSql\\Ir\\Expression\\WideIntegerLiteral", "-9223372036854775808", "int8",
                wide, 0, 0,
                'INSERT INTO "public"."signed_bounds" ("wide", "id") VALUES '
                '(CAST(-9223372036854775808 AS pg_catalog.int8), CAST(7 AS pg_catalog.int4)) RETURNING "wide" AS "low", "id" AS "id"',
                wide, wide,
                'UPDATE "public"."signed_bounds" AS "__ztd_source" SET "wide" = "__ztd_source"."id", '
                '"id" = "__ztd_source"."wide" RETURNING "wide" AS "wide"',
            ], "installed-postgres-signed-writes-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Select"):
            local = 'SELECT "__ztd_source"."id" AS "id" FROM "public"."ledger" AS "__ztd_source"'
            require(item.get("postgresLocalCatalog") == ["public", "ledger", 0, local, local, True],
                    "installed-postgres-local-catalog-mismatch", item)
            require(item.get("outputOrder") == [
                [[1, True, False], [2, False, False]], [2, 1],
                'SELECT "__ztd_source"."lo" AS "id", "__ztd_source"."id" AS "signed" '
                'FROM "public"."bounds_read" AS "__ztd_source" ORDER BY 1 DESC NULLS LAST, 2 ASC NULLS LAST LIMIT 2 OFFSET 1',
            ], "installed-postgres-output-order-mismatch", item)
            require(item.get("sourceOrder") == [
                "SqlSemantics\\Platform\\PostgreSql\\Ir\\From\\WideIntegerColumnReference", 1, 0, 1,
                'SELECT "__ztd_source"."id" AS "lo" FROM "public"."bounds_read" AS "__ztd_source" '
                'ORDER BY "__ztd_source"."lo" DESC NULLS LAST, 1 ASC NULLS LAST LIMIT 2 OFFSET 1',
                [1, 2],
                'SELECT DISTINCT "__ztd_source"."lo" AS "value", "__ztd_source"."id" AS "id" '
                'FROM "public"."bounds_read" AS "__ztd_source" ORDER BY 1 DESC NULLS FIRST, 2 ASC NULLS LAST',
            ], "installed-postgres-source-order-mismatch", item)
            pg_ir = "SqlSemantics\\Platform\\PostgreSql\\Ir\\"
            require(item.get("signedComparison") == [
                pg_ir + "Predicate\\IntegerComparison", pg_ir + "From\\WideIntegerColumnReference", 1,
                pg_ir + "ColumnReference", 0, "<",
                'SELECT ("__ztd_source"."lo" OPERATOR(pg_catalog.>) "__ztd_source"."id") AS "result" '
                'FROM "public"."bounds_read" AS "__ztd_source" '
                'WHERE ("__ztd_source"."id" OPERATOR(pg_catalog.<) "__ztd_source"."lo")',
            ], "installed-postgres-signed-comparison-mismatch", item)
            require(item.get("groupedBounds") == [
                pg_ir + "Aggregation\\GroupedSelect", [[2, True, False], [1, False, False]], [2, 1],
                'SELECT "__ztd_source"."id" AS "owner", pg_catalog.count(*) AS "n" FROM "public"."group_bounds" AS "__ztd_source" '
                'GROUP BY "__ztd_source"."id" ORDER BY 2 DESC NULLS LAST, 1 ASC NULLS LAST LIMIT 2 OFFSET 1',
            ], "installed-postgres-group-ordered-result-mismatch", item)
            require(item.get("externalSourceKinds") == [
                "view", "materialized-view", True, "int8",
                'SELECT "__ztd_source"."id" AS "id" FROM "public"."active_rows" AS "__ztd_source"',
                'SELECT "__ztd_source"."id" AS "id" FROM ONLY "public"."saved_rows" AS "__ztd_source"',
                "A DELETE target must be a base table.",
            ], "installed-postgres-external-source-kinds-mismatch", item)
            require(item.get("groupedHaving") == [
                pg_ir + "Aggregation\\GroupedComparison", pg_ir + "Aggregation\\Aggregate", "sum", 1,
                ">", pg_ir + "Expression\\WideIntegerLiteral", "2147483649",
                'SELECT "__ztd_source"."id" AS "key" FROM "public"."having_input" AS "__ztd_source" '
                'GROUP BY "__ztd_source"."id" HAVING (pg_catalog.sum("__ztd_source"."amount") '
                "OPERATOR(pg_catalog.>) CAST(2147483649 AS pg_catalog.int8)) ORDER BY 1 ASC NULLS LAST",
            ], "installed-postgres-grouped-having-mismatch", item)
            require(item.get("signedRangeWidths") == [
                pg_ir + "Predicate\\IntegerDistinctness", pg_ir + "From\\WideIntegerColumnReference", 0,
                pg_ir + "Expression\\WideIntegerNull", True,
                'SELECT COALESCE(("__ztd_source"."amount" OPERATOR(pg_catalog.=) CAST(NULL AS pg_catalog.int8)), '
                '("__ztd_source"."amount" IS NULL AND CAST(NULL AS pg_catalog.int8) IS NULL)) AS "exact_null" '
                'FROM "public"."range_fields" AS "__ztd_source"',
            ], "installed-postgres-signed-range-widths-mismatch", item)
            require(item.get("withProjection") == [
                "MATERIALIZED", 0, 1, 0,
                'WITH "__ztd_cte_0" AS (SELECT CAST(42 AS pg_catalog.int4) AS "__ztd_c0"), '
                '"__ztd_cte_1" AS MATERIALIZED (SELECT "__ztd_cte_0"."__ztd_c0" AS "__ztd_c0" FROM "__ztd_cte_0") '
                'SELECT "__ztd_cte_1"."__ztd_c0" AS "answer" FROM "__ztd_cte_1"',
            ], "installed-postgres-with-projection-mismatch", item)
            require(item.get("int8Field") == [
                "SqlSemantics\\Platform\\PostgreSql\\Ir\\From\\WideIntegerColumnReference", 0, 0, True, True, "int4", "int8",
                'SELECT "__ztd_source"."lo" AS "grouped", "__ztd_source"."lo" AS "resolved", '
                'CAST("__ztd_source"."lo" AS pg_catalog.int4) AS "narrow" FROM "public"."bounds_read" AS "__ztd_source"',
            ], "installed-postgres-int8-field-mismatch", item)
            require(item.get("booleanFields") == [
                "SqlSemantics\\Platform\\PostgreSql\\Ir\\From\\BooleanColumnReference", True, True, True,
                "IS UNKNOWN", ["bool", "int4"],
                'SELECT "__ztd_source"."enabled" AS "first", "__ztd_source"."enabled" AS "second", "__ztd_source"."id" AS "id" '
                'FROM "public"."flags" AS "__ztd_source" WHERE ("__ztd_source"."enabled" IS UNKNOWN)',
            ], "installed-postgres-boolean-fields-mismatch", item)
            require(item.get("nestedCast") == ["int4", "bool", "42"], "installed-builtin-cast-mismatch", item)
            require(item.get("wideCast") == ["int4", "int8", "2147483648", "-9223372036854775808"],
                    "installed-bigint-role-mismatch", item)
            require(item.get("typedParameters") == [[2, "int4"], [1, "int8"], [1, "int8", "int4"]],
                    "installed-binding-input-mismatch", item)
            require(item.get("textLiteral") == ["it's \\値", "sample", "SELECT CAST('it''s \\値' AS pg_catalog.text) AS \"sample\""],
                    "installed-text-literal-mismatch", item)
            require(item.get("textMarker") == [1, "text", 'SELECT CAST($1 AS pg_catalog.text) AS "a0"'],
                    "installed-text-marker-mismatch", item)
            require(item.get("typedColumns") == [
                ["text", "int4"],
                [["From\\TextColumnReference", 0, 0, "label"], ["From\\TextColumnReference", 0, 0, "label"],
                 ["ColumnReference", 1, 0, "id"]],
                'SELECT "__ztd_source"."label" AS "label", "__ztd_source"."label" AS "label", '
                '"__ztd_source"."id" AS "id" FROM "public"."typed_table" AS "__ztd_source"',
            ], "installed-typed-columns-mismatch", item)
            require(item.get("derivedText") == ["text", "int4", 0, 1, "label", True],
                    "installed-derived-text-mismatch", item)
            require(item.get("typedReturning") == [["From\\TextColumnReference", 0, "removed"], ["ColumnReference", 1, "id"]],
                    "installed-typed-returning-mismatch", item)
            require(item.get("typedUpdate") == [["From\\TextColumnReference", 0, 1], ["From\\TextColumnReference", 1, 0]],
                    "installed-typed-update-mismatch", item)
            require(item.get("typedInsert") == [[["text", 0], ["int4", 1]], "", "4"],
                    "installed-typed-insert-mismatch", item)
            require(item.get("typedInsertQuery") == [["text", "int4"], True, True],
                    "installed-typed-insert-query-mismatch", item)
            require(item.get("nullableKinds") == [["text", None]] * 3,
                    "installed-nullable-column-kind-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\CreateTable"):
            namespace = "SqlSemantics\\Platform\\MySql\\Ir\\Schema\\"
            declaration = ("CREATE TABLE IF NOT EXISTS `app`.`varchar_decl` (`n` SMALLINT NOT NULL DEFAULT -7, "
                           "`label` VARCHAR(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL DEFAULT 'é  ') "
                           "ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin")
            require(item.get("mysqlVarcharDeclaration") == [
                namespace + "CreateTable", "app", "varchar_decl", True, "utf8mb4_bin", "SMALLINT",
                namespace + "CharacterStorage", 1, "utf8mb4_bin", True, "é  ", declaration, declaration,
                "mysql-9.1.0/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb",
            ], "installed-mysql-varchar-declaration-mismatch", item)
            ir = "SqlSemantics\\Platform\\MySql\\Ir\\"
            narrow, wide = ir + "IntegerLiteral", ir + "Predicate\\RangeIntegerLiteral"
            require(item.get("mysqlSignedDeclaration") == [
                [["TINYINT", 1, True, narrow, "-128"], ["MEDIUMINT", 3, True, narrow, "8388607"],
                 ["BIGINT", 8, False, wide, "9223372036854775807"], ["BIGINT", 8, True, wide, "-9223372036854775808"]],
                "CREATE TABLE `app`.`signed_decl` (`a` TINYINT NULL DEFAULT -128, `b` MEDIUMINT NULL DEFAULT 8388607, "
                "`c` BIGINT NOT NULL DEFAULT 9223372036854775807, `d` BIGINT NULL DEFAULT -9223372036854775808) ENGINE=InnoDB",
                "The TINYINT default is outside the signed storage range.",
            ], "installed-mysql-signed-declaration-mismatch", item)
            require(item.get("mysqlPrimaryKey") == [
                namespace + "Key\\PrimaryKey", namespace + "Key\\PrimaryKeyColumn", [[1, True], [0, False]], True, False, None,
                "CREATE TABLE `app`.`key_decl` (`lo` SMALLINT NOT NULL, `hi` BIGINT NOT NULL DEFAULT -7, "
                "PRIMARY KEY (`hi` DESC, `lo`)) ENGINE=InnoDB",
            ], "installed-mysql-primary-key-mismatch", item)
            temporary = "CREATE TEMPORARY TABLE IF NOT EXISTS `app`.`scratch` (`n` SMALLINT NOT NULL DEFAULT -7) ENGINE=InnoDB"
            require(item.get("mysqlSessionCreateTable") == ["app", "scratch", "temporary-table", True, temporary, temporary],
                    "installed-mysql-session-declaration-mismatch", item)
            require(item.get("mysqlInnoDbDeclarationWidth") == [
                1017, 1017, 1016, "c1016", True, "The declared clustered InnoDB record must fit the native 8126-byte boundary.",
            ], "installed-mysql-innodb-width-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\Index\\CreateIndex"):
            index_sql = "CREATE UNIQUE INDEX `test_ix` ON `app`.`session_index` (`a` DESC) INVISIBLE ALGORITHM=COPY LOCK=NONE"
            require(item.get("mysqlBtreeIndex") == ["temporary-table", "int-not-null", "unique", "unique", True, False,
                    index_sql, index_sql, "An implicit primary index must be visible."], "installed-mysql-btree-index-mismatch", item)
            require(item.get("mysqlIndexNamespace") == ["COPY", "NONE",
                    "CREATE INDEX requires an unoccupied native index name."], "installed-mysql-index-namespace-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\Index\\DropIndex"):
            drop = "DROP INDEX `Élan_2` ON `app`.`drop_owner` ALGORITHM=COPY LOCK=NONE"
            require(item.get("mysqlDropIndex") == ["temporary-table", "Élan_2", "clustered-unique", drop, drop, "primary",
                    "DROP INDEX `PRIMARY` ON `app`.`primary_drop`",
                    "DROP INDEX requires one resolved ordinary index on its owning table."], "installed-mysql-drop-index-mismatch", item)
            require(item.get("mysqlNativeIndexCase") == ["Élan", "CREATE INDEX `Élan_plus` ON `app`.`drop_owner` (`Élan` DESC)"],
                    "installed-mysql-native-index-case-mismatch", item)
            require(item.get("mysqlIndexRemovalPolicy") == ["COPY", "NONE",
                    "The algorithm and lock cannot remove this native InnoDB index."], "installed-mysql-index-removal-policy-mismatch", item)
            legacy = ("ALTER IGNORE TABLE `app`.`legacy_drop` DROP INDEX `ordered_key`"
                      ", ALGORITHM=COPY, LOCK=NONE")
            require(item.get("mysqlAlterIndexRemoval") == ["temporary-table", True, "ordered_key", legacy, legacy],
                    "installed-mysql-alter-index-removal-mismatch", item)
            generation_drop = "DROP INDEX `PRIMARY` ON `app`.`keeps_value`"
            require(item.get("mysqlIndexAutoIncrement") == [
                "PRIMARY", "primary", True, True, generation_drop, generation_drop,
                "DROP INDEX must retain a backing key for the AUTO_INCREMENT field.",
            ], "installed-mysql-auto-increment-support-mismatch", item)
            dropping_fk = "DROP INDEX `first_key` ON `app`.`fk_index_parent`"
            preserves_fk = "ALTER TABLE `app`.`fk_index_parent` DROP INDEX `first_key`, RENAME INDEX `loose_support` TO `moved`"
            blocked_fk = "Index removal must retain a supporting key for each native foreign-key side."
            require(item.get("mysqlIndexForeignRequirements") == [
                dropping_fk, dropping_fk, "loose_support", preserves_fk, [blocked_fk] * 3,
            ], "installed-mysql-index-foreign-key-support-mismatch", item)
            copied_fk = "DROP INDEX `Actual_Fk` ON `app`.`generated_copy`"
            require(item.get("mysqlImplicitForeignIndexes") == [
                "Actual_Fk", copied_fk, copied_fk, "ALTER TABLE `app`.`generated_child` ALTER INDEX `Actual_Fk` INVISIBLE",
                [blocked_fk, "CREATE INDEX requires an unoccupied native index name."],
            ], "installed-mysql-implicit-foreign-key-index-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\Index\\DropIndexes"):
            drop_set = "ALTER TABLE `app`.`batch_index` DROP INDEX `PRIMARY`, DROP INDEX `by_a`"
            require(item.get("mysqlAtomicIndexRemoval") == [
                ["PRIMARY", "by_a"], ["primary", "ordinary"], True, drop_set,
                "Atomic removal must retain a backing key for the AUTO_INCREMENT field.",
            ] and item["sql"] == drop_set, "installed-mysql-atomic-index-removal-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\Index\\Rename\\RenameIndexes"):
            rename = ("ALTER TABLE `app`.`index_rename_probe` RENAME INDEX `by_a` TO `by_b`, "
                      "RENAME INDEX `by_b` TO `by_a`")
            require(item.get("mysqlIndexRename") == [
                [["by_a", "by_b"], ["by_b", "by_a"]], rename,
                "The resulting native index names must be distinct.",
                "Native index renaming requires MySQL 5.7 or later.", True,
            ] and item["sql"] == rename, "installed-mysql-index-rename-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\Index\\Change\\AlterIndexes"):
            mixed = "ALTER TABLE `app`.`batch_index` DROP INDEX `PRIMARY`, RENAME INDEX `by_a` TO `new_a`"
            require(item.get("mysqlMixedIndexAlteration") == [
                "PRIMARY", True, ["by_a", "new_a"], mixed,
                "A removed index cannot also be renamed or unmoved.",
                "Mixed native index removal and renaming requires MySQL 5.7 or later.",
            ] and item["sql"] == mixed, "installed-mysql-mixed-index-alteration-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\Query\\CreateTableAs"):
            require(item.get("mysqlQueryBackedInnoDbWidth") == [
                203, False, False, 1, "tail_5", "The projected clustered InnoDB record must fit the native 8126-byte boundary.",
            ], "installed-mysql-query-backed-width-mismatch", item)
            create_as = ("CREATE TEMPORARY TABLE IF NOT EXISTS `app`.`result_probe` ENGINE=InnoDB "
                         "AS SELECT CAST(17 AS SIGNED) AS `n`, NULL AS `absent`")
            require(item.get("mysqlCreateTableAs") == ["app", "result_probe", "temporary-table", True, ["n", "absent"],
                    "SqlSemantics\\Platform\\MySql\\Ir\\Expression\\SignedCast",
                    "mysql-8.4.7/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb",
                    create_as, create_as, True], "installed-mysql-create-table-as-mismatch", item)
            source = ("CREATE TABLE `app`.`bound_probe` ENGINE=InnoDB AS SELECT `__ztd_source`.`id` AS `a`, "
                      "`__ztd_source`.`id` AS `b` FROM `app`.`ctas_probe` AS `__ztd_source`")
            require(item.get("mysqlCreateTableAsSource") == ["app", "ctas_probe", "temporary-table",
                    [["a", 0, 0], ["b", 0, 0]], source], "installed-mysql-create-table-as-source-mismatch", item)
            text_source = ("CREATE TABLE `app`.`text_copy` ENGINE=InnoDB AS SELECT `__ztd_source`.`v` AS `a`, "
                           "`__ztd_source`.`v` AS `b`, `__ztd_source`.`z` AS `c`, `__ztd_source`.`z` AS `d` "
                           "FROM `app`.`text_source` AS `__ztd_source`")
            require(item.get("mysqlCreateTableAsVarchar") == [[[8191, "utf8mb4_bin"], [0, "utf8mb4_bin"]], text_source, True],
                    "installed-mysql-create-table-as-varchar-mismatch", item)
            declared = ("CREATE TABLE `app`.`declared_copy` (`pre` SMALLINT NULL DEFAULT 7, `N` TINYINT NOT NULL) "
                        "ENGINE=InnoDB AS SELECT NULL AS `n`")
            require(item.get("mysqlCreateTableAsDeclaration") == [["pre", "N"], declared, declared, True],
                    "installed-mysql-create-table-as-explicit-layout-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\Like\\CreateTableLike"):
            copy = "CREATE TEMPORARY TABLE IF NOT EXISTS `app`.`copy_probe` LIKE `app`.`accounts`"
            require(item.get("mysqlCreateLike") == ["app", "copy_probe", "temporary-table", "app", "accounts",
                    "table", True, copy, copy, True], "installed-mysql-create-like-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Select"):
            require(item.get("postgresSignedMembership") == [True, True, ["a"], ["2147483648", None, "2147483648"], True,
                'SELECT (NOT ("__ztd_source"."a" OPERATOR(pg_catalog.=) ANY (CAST(ARRAY[CAST(2147483648 AS pg_catalog.int8), '
                'CAST(NULL AS pg_catalog.int4), CAST(2147483648 AS pg_catalog.int8)] AS pg_catalog.int8[])))) AS "selected" '
                'FROM "public"."membership_release" AS "__ztd_source"'],
                "installed-postgres-signed-membership-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\CreateTable"):
            require(item.get("postgresTextStorage") == ["pg_catalog.text", False, "quote's",
                    'CREATE TABLE "public"."text_decl" ("label" pg_catalog.text NOT NULL DEFAULT CAST(\'quote\'\'s\' AS pg_catalog.text)) USING heap'],
                    "installed-postgres-text-storage-mismatch", item)
            require(item.get("postgresCollatedTable") == ["C", None, "C", "C", "codes",
                'CREATE TABLE "public"."declared_c" ("code" pg_catalog.text COLLATE pg_catalog."C" NOT NULL, '
                '"payload" pg_catalog.text NULL) USING heap'],
                    "installed-postgres-collated-table-mismatch", item)
            require(item.get("postgresStockLookup") == ["C", "C", True, True,
                    'CREATE SCHEMA "lookup_owner" AUTHORIZATION CURRENT_USER CREATE TABLE "lookup_owner"."codes" '
                    '("label" pg_catalog.text COLLATE pg_catalog."C" NULL) USING heap CREATE INDEX "choice" '
                    'ON "lookup_owner"."codes" USING btree ("label" COLLATE pg_catalog."C" pg_catalog.text_ops ASC NULLS LAST)',
                    ["The column requires a resolved stock PostgreSQL collation.",
                     "An explicitly collated PostgreSQL B-tree key requires stock pg_catalog.C and its default text operator class."]],
                    "installed-postgres-stock-lookup-mismatch", item)
            wide = "SqlSemantics\\Platform\\PostgreSql\\Ir\\Expression\\WideIntegerLiteral"
            require(item.get("postgresSignedDefault") == [
                [["SMALLINT", wide, "-9223372036854775808"], ["BIGINT", wide, "7"], ["pg_catalog.text", wide, "9223372036854775807"]],
                'CREATE TABLE "public"."signed_default_decl" ("minimum" SMALLINT NULL DEFAULT CAST(-9223372036854775808 AS pg_catalog.int8), '
                '"source_width" BIGINT NULL DEFAULT CAST(7 AS pg_catalog.int8), '
                '"rendered" pg_catalog.text NULL DEFAULT CAST(9223372036854775807 AS pg_catalog.int8)) USING heap',
            ], "installed-postgres-signed-default-mismatch", item)
            ir = "SqlSemantics\\Platform\\PostgreSql\\Ir\\"
            role, operand = ir + "Schema\\BooleanStorage", ir + "BooleanLiteral"
            require(item.get("postgresBooleanDefault") == [
                [[role, "pg_catalog.bool", False, operand, True], [role, "pg_catalog.bool", True, operand, False]],
                'CREATE TABLE "public"."bool_decl" ("enabled" pg_catalog.bool NOT NULL DEFAULT TRUE, '
                '"disabled" pg_catalog.bool NULL DEFAULT FALSE) USING heap',
            ], "installed-postgres-boolean-default-mismatch", item)
            key = ('CREATE TABLE "public"."checked_key" ("a" INTEGER NOT NULL, "b" BIGINT NOT NULL, '
                   '"note" pg_catalog.text NULL, CONSTRAINT "my key" PRIMARY KEY ("b", "a") INCLUDE ("note", "b", "note") '
                   'DEFERRABLE INITIALLY DEFERRED) USING heap')
            require(item.get("postgresPrimaryKey") == [
                ["b", "a"], ["note", "b", "note"], "my key", "DEFERRABLE INITIALLY DEFERRED", [False, False, True], key, key, True,
            ], "installed-postgres-primary-key-mismatch", item)
            unique = ('CREATE TABLE "public"."retained_unique" ("a" INTEGER NULL, "b" INTEGER NOT NULL, '
                      'CONSTRAINT "transferred" PRIMARY KEY ("b") NOT DEFERRABLE, UNIQUE NULLS NOT DISTINCT ("a") NOT DEFERRABLE, '
                      'CONSTRAINT "deferred_unique" UNIQUE NULLS DISTINCT ("a") DEFERRABLE INITIALLY DEFERRED) USING heap')
            require(item.get("postgresUniqueKey") == [
                "transferred", [[["a"], None, True, "NOT DEFERRABLE"],
                                [["a"], "deferred_unique", False, "DEFERRABLE INITIALLY DEFERRED"]],
                unique, unique, True,
            ], "installed-postgres-unique-key-mismatch", item)
            require(item.get("postgresTableWidth") == [
                0, 'CREATE TABLE "public"."empty_decl" () USING heap', 1600, 1600, "field_1", "field_1600", True, True,
            ], "installed-postgres-table-width-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Schema\\CreateTable"):
            require(item.get("keyLayout") == [
                ["primary", True, 0, False, "BINARY", None], ["unique", True, 1, False, "NOCASE", None],
                ["unique", False, 1, True, "BINARY", "IGNORE"],
            ], "installed-table-key-layout-mismatch", item)
            require(item.get("namedChecks") == [["ref id", 1, 0]], "installed-table-check-mismatch", item)
            require(item.get("foldedCheckBindings") == ["07", "1", "007"], "installed-folded-check-binding-mismatch", item)
            require(item.get("generatedColumns") == [[0, "STORED", 2], [2, "VIRTUAL", 1]],
                    "installed-generated-column-mismatch", item)
            require(item.get("implicitCheck") == ["a", "==", False, " ", "", 1, "=", 0],
                    "installed-implicit-check-presentation-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Schema\\View\\CreateView"):
            require(item.get("createView") == ["main", "view_result", ["column1"], ["main", "accounts"], False],
                    "installed-view-definition-mismatch", item)
            require(item.get("groupedView") == [["id", "n"], 0, 1, "BINARY",
                                               "sqlite-3.47.2/utf8/short-column-names/numbered-parameters/grouped-builtin"],
                    "installed-grouped-view-definition-mismatch", item)
            require(item.get("compoundView") == [["selected"], "UNION ALL",
                                                'CREATE VIEW "main"."view_set" ("selected") AS VALUES (4), (4) UNION ALL SELECT 2 AS "item"'],
                    "installed-compound-view-definition-mismatch", item)
            require(item.get("localView") == [["first", "second"], True, 0, 1, 1],
                    "installed-local-view-definition-mismatch", item)
            require(item.get("mergedJoins") == [
                "FULL", ["Key", "original", "other"], [[0, 0], [1, 0]], "RIGHT", [["Key", 1], ["original", 0]],
            ], "installed-merged-join-definition-mismatch", item)
        if item["role"] == "Schema\\DropView":
            require(item.get("dropView") == ["main", "removable", True], "installed-view-removal-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\Alter\\RenameTable"):
            origin = ["app", "accounts"]
            staged = ["app", "staged"]
            require(item.get("mysqlRenameMoves") == [[origin, staged, origin], [staged, ["app", "archived"], origin]]
                    and item["sql"] == "RENAME TABLE `app`.`accounts` TO `app`.`staged`, `app`.`staged` TO `app`.`archived`",
                    "installed-mysql-ordered-rename-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Select"):
            require(item.get("mysqlSessionTableSources") == ["app", "accounts", "temporary-table", 0, 0, "temporary-table"]
                    and item.get("mysqlSessionRename") == ["app", "accounts", "archived"],
                    "installed-mysql-session-table-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\View\\DropView"):
            require(item.get("mysqlShadowedViewRemoval") == [
                ["app", "shadowed_view", False, "DROP VIEW `app`.`shadowed_view`"],
                ["app", "session_only", True, "DROP VIEW IF EXISTS `app`.`session_only`"],
            ], "installed-mysql-shadowed-view-removal-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\DropTable"):
            require(item.get("mysqlSessionDrop") == [
                [False, True, ["table"], "DROP TABLE IF EXISTS `app`.`accounts`"],
                [False, False, ["temporary-table", "table"], "DROP TABLE `app`.`accounts`, `app`.`retained`"],
                [True, True, ["temporary-table", "absent"], "DROP TEMPORARY TABLE IF EXISTS `app`.`accounts`, `app`.`retained`"],
            ], "installed-mysql-session-drop-mismatch", item)
            require(item.get("mysqlDropTableDependencies") == [
                "DROP TABLE `app`.`drop_parent`, `app`.`drop_child`",
                "DROP TABLE `app`.`drop_parent`, `app`.`drop_child`",
                "DROP TABLE `app`.`drop_child`, `app`.`drop_parent`",
                "DROP TABLE must include every referencing persistent child.",
                "DROP TABLE must include every referencing persistent child.",
                "This MySQL release must drop a referencing child before its parent.",
                "DROP TABLE must include every referencing persistent child.",
            ], "installed-mysql-drop-table-dependency-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\ForeignKey\\Add\\AddForeignKeys"):
            expected_sql = ("ALTER TABLE `app`.`fk_add_child` "
                            "ADD CONSTRAINT `Live_Link` FOREIGN KEY (`x`) REFERENCES `app`.`fk_add_parent` (`a`) "
                            "MATCH FULL ON UPDATE NO ACTION ON DELETE CASCADE, "
                            "ADD FOREIGN KEY (`y` DESC) REFERENCES `app`.`fk_add_parent` (`b`) "
                            "MATCH PARTIAL ON UPDATE SET NULL ON DELETE NO ACTION")
            require(item.get("mysqlAddForeignKeys") == [
                "`app`.`fk_add_parent`", "`app`.`fk_add_parent`", "BIGINT", True, "FULL", "PARTIAL",
                "Live_Link", "fk_add_child_ibfk_1", "Live_Link", None, None,
                expected_sql, expected_sql, [expected_sql],
                ["The checked InnoDB profile needs copying and a compatible lock to add a native foreign key."],
            ] and item["sql"] == expected_sql, "installed-mysql-add-foreign-keys-mismatch", item)
            string_sql = ("ALTER TABLE `app`.`storage_child` "
                          "ADD CONSTRAINT `Text_Link` FOREIGN KEY (`x`) REFERENCES `app`.`storage_parent` (`p`) ON UPDATE NO ACTION ON DELETE NO ACTION, "
                          "ADD CONSTRAINT `Byte_Link` FOREIGN KEY (`y`) REFERENCES `app`.`storage_parent` (`q`) ON UPDATE NO ACTION ON DELETE NO ACTION")
            require(item.get("mysqlForeignKeyStringDomains") == [
                [["VARCHAR", 8, "utf8mb4_unicode_ci", "CHAR", 4, "utf8mb4_unicode_ci", 32],
                 ["VARBINARY", 8, None, "BINARY", 4, None, 8]], string_sql,
                ["The full foreign-key field exceeds the native InnoDB index field limit."],
            ], "installed-mysql-foreign-key-string-domain-mismatch", item)
            scalar_sql = ("ALTER TABLE `app`.`scalar_child` "
                          "ADD CONSTRAINT `Packed_Link` FOREIGN KEY (`x`) REFERENCES `app`.`scalar_parent` (`p`) ON UPDATE NO ACTION ON DELETE NO ACTION, "
                          "ADD CONSTRAINT `Date_Link` FOREIGN KEY (`y`) REFERENCES `app`.`scalar_parent` (`q`) ON UPDATE NO ACTION ON DELETE NO ACTION, "
                          "ADD CONSTRAINT `Time_Link` FOREIGN KEY (`z`) REFERENCES `app`.`scalar_parent` (`r`) ON UPDATE NO ACTION ON DELETE NO ACTION")
            require(item.get("mysqlForeignKeyScalarDimensions") == [
                ["DECIMAL", [1, 65, True, False]], ["BIT", [1, 64, False, False]],
                ["FLOAT", [1, 255, True, True]], ["DOUBLE", [1, 255, True, True]],
                ["DATE", [None, None, False, False]], ["YEAR", [None, None, False, False]],
                ["TIME", [0, 6, False, False]], ["DATETIME", [0, 6, False, False]],
                ["TIMESTAMP", [0, 6, False, False]],
            ], "installed-mysql-foreign-key-scalar-dimensions-mismatch", item)
            require(item.get("mysqlForeignKeyScalarDomains") == [
                ["VARBINARY", 4, "DECIMAL", 9, 0, 4], ["DATE", "MEDIUMINT", False, 3], ["TIME", 1, "TIME", 2, 4], scalar_sql,
            ], "installed-mysql-foreign-key-scalar-domain-mismatch", item)
            enumeration_sql = ("ALTER TABLE `app`.`label_child` "
                               "ADD CONSTRAINT `Ordinal_Link` FOREIGN KEY (`x`) REFERENCES `app`.`label_parent` (`p`) ON UPDATE NO ACTION ON DELETE NO ACTION, "
                               "ADD CONSTRAINT `Mask_Link` FOREIGN KEY (`y`) REFERENCES `app`.`label_parent` (`q`) ON UPDATE NO ACTION ON DELETE NO ACTION")
            require(item.get("mysqlForeignKeyEnumerationDomains") == [
                [["ENUM", ["green", " red", ""], "utf8mb4_bin", "TINYINT", 1],
                 ["SET", list("abcdefghi"), "utf8mb4_bin", "SMALLINT", 2]], enumeration_sql,
            ], "installed-mysql-foreign-key-enumeration-domain-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\ForeignKey\\Exchange\\AlterForeignKeys"):
            exchange = ("ALTER TABLE `app`.`exchange_child` DROP FOREIGN KEY `exchange_child_ibfk_1`, "
                        "ADD CONSTRAINT `Wider_Link` FOREIGN KEY (`pid`, `other_id`) REFERENCES `app`.`exchange_parent` "
                        "(`a`, `b`) ON UPDATE CASCADE ON DELETE SET NULL, ALGORITHM=COPY")
            require(item.get("mysqlForeignKeyExchange") == [
                "exchange_child_ibfk_1", "Wider_Link", "Wider_Link", ["pid"], ["pid"], exchange, exchange,
            ] and item["sql"] == exchange, "installed-mysql-foreign-key-exchange-mismatch", item)
            supporting = ("ALTER TABLE `app`.`indexed_exchange` ADD INDEX `pid` (`spare`), "
                          "ADD FOREIGN KEY (`pid`) REFERENCES `app`.`exchange_parent` (`a`) "
                          "ON UPDATE NO ACTION ON DELETE NO ACTION")
            require(item.get("mysqlMixedSupportingKeys") == [
                "pid", "ordinary", False, ["spare"], "indexed_exchange_ibfk_1", "pid_2", supporting, supporting,
            ], "installed-mysql-mixed-supporting-key-mismatch", item)
            physical = ("ALTER TABLE `app`.`sequence_child` DROP INDEX `old_seq`, "
                        "ADD CONSTRAINT `replacement_edge` FOREIGN KEY (`seq`) "
                        "REFERENCES `app`.`sequence_parent` (`id`) ON UPDATE NO ACTION ON DELETE NO ACTION")
            require(item.get("mysqlMixedPhysicalExchange") == [
                "old_seq", "ordinary", True, "replacement_edge", [], physical, physical,
            ], "installed-mysql-mixed-physical-exchange-mismatch", item)
            recluster = ("ALTER TABLE `app`.`rebuilt` DROP INDEX `PRIMARY`, DROP INDEX `source`, "
                         "ADD UNIQUE INDEX `substitute` (`cand`, `hid`), ADD CONSTRAINT `Outgoing` "
                         "FOREIGN KEY (`probe`) REFERENCES `app`.`upstream` (`id`) ON UPDATE NO ACTION ON DELETE NO ACTION")
            require(item.get("mysqlMixedClusteredReplacement") == [
                "retained", "clustered-unique", True, recluster,
            ], "installed-mysql-mixed-clustered-replacement-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\ForeignKey\\DropForeignKeys"):
            prefix = "ALTER TABLE `app`.`fk_drop_child` "
            mixed = prefix + "DROP INDEX `Owner_Link`, DROP FOREIGN KEY `Owner_Link`"
            require(item.get("mysqlDropForeignKeys") == [
                "table", "Owner_Link", "ordinary", "Owner_Link", "constraint", mixed, mixed,
                [mixed, prefix + "DROP FOREIGN KEY `Owner_Link`"],
                "temporary-table", None, "ignored-on-temporary", prefix + "DROP FOREIGN KEY ``",
                ["This MySQL release cannot repeat a foreign-key removal."],
            ] and item["sql"] == mixed, "installed-mysql-drop-foreign-keys-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\Index\\Visibility\\SetIndexVisibility"):
            changes = ("ALTER TABLE `app`.`visibility_probe` ALTER INDEX `first_key` INVISIBLE, ALTER INDEX `first_key` VISIBLE, "
                       "ALTER INDEX `second_key` INVISIBLE, ALGORITHM=COPY, LOCK=NONE")
            require(item.get("mysqlIndexVisibility") == [
                "temporary-table", [["first_key", "ordinary", False], ["first_key", "ordinary", True], ["second_key", "unique", False]],
                changes, changes,
                ["Native index visibility requires MySQL 8.0 or later.", "Native index visibility requires a secondary key."],
            ], "installed-mysql-index-visibility-mismatch", item)
        if (item["platform"], item["role"]) == ("MySql", "Schema\\Truncate\\TruncateTable"):
            require(item.get("mysqlTruncate") == ["app", "accounts", "table", "temporary-table"]
                    and item["sql"] == "TRUNCATE TABLE `app`.`accounts`",
                    "installed-mysql-truncate-mismatch", item)
            refused = "TRUNCATE TABLE is blocked by a foreign key from another table."
            require(item.get("mysqlTruncateDependencies") == [
                "TRUNCATE TABLE `app`.`fk_parent`", "temporary-table",
                [refused, refused, "TRUNCATE TABLE requires a resolved base table.", refused],
            ], "installed-mysql-truncate-dependency-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Truncate\\TruncateTable"):
            require(item.get("postgresTruncate") == [True, True, [["public", "accounts", True], ["public", "accounts", False]]]
                    and item["sql"] == 'TRUNCATE TABLE ONLY "public"."accounts", "public"."accounts" RESTART IDENTITY CASCADE',
                    "installed-postgres-truncate-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Alter\\RenameTable"):
            require(item.get("postgresRenameTable") == ["public", "accounts", "archived", False]
                    and item["sql"] == 'ALTER TABLE "public"."accounts" RENAME TO "archived"',
                    "installed-postgres-rename-table-mismatch", item)
        if (item["platform"], item["role"]) == ("PostgreSql", "Schema\\Alter\\RenameColumn"):
            require(item.get("postgresRenameColumn") == ["public", "accounts", "id", "new Id", True, True]
                    and item["sql"] == 'ALTER TABLE IF EXISTS ONLY "public"."accounts" RENAME COLUMN "id" TO "new Id"',
                    "installed-postgres-rename-column-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Schema\\Alter\\RenameTable"):
            require(item.get("rename") == ["main", "accounts", "renamed"], "installed-table-rename-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Schema\\Alter\\RenameColumn"):
            require(item.get("rename") == ["main", "accounts", "id", "renamed", False],
                    "installed-column-rename-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Schema\\Alter\\AddColumn"):
            require(item.get("addColumn") == [
                "main", "accounts", 1, [], False, "added", "TEXT", "NOCASE", "value", True,
                """ALTER TABLE "main"."accounts" ADD COLUMN "added" TEXT DEFAULT 'value' COLLATE NOCASE""",
            ] and item["sql"] == item["addColumn"][-1], "installed-sqlite-add-column-mismatch", item)
            require(item.get("computedDefaults") == [
                "-", "-", "+",
                'ALTER TABLE "main"."accounts" ADD COLUMN "total" INTEGER DEFAULT ((7 - (5 - 2)))',
                'CREATE TABLE "main"."calculated" ("amount" TEXT DEFAULT ((2 + 3)))',
            ], "installed-sqlite-computed-default-mismatch", item)
            require(item.get("appendedChecks") == [
                "minimum", 1, 1, 0, [[0, "id"], [1, "total"]],
                'ALTER TABLE "main"."accounts" ADD COLUMN "total" INTEGER DEFAULT 7 CONSTRAINT "minimum" CHECK (("total" > "id"))',
            ], "installed-sqlite-ordinary-appended-check-mismatch", item)
            require(item.get("implicitAppendedCheck") == [
                "TOTAL", ">=", 2, 1, [[1, "total"]], 'ALTER TABLE "main"."accounts" ADD COLUMN "total" INTEGER CHECK (TOTAL >= 007)',
            ], "installed-sqlite-implicit-appended-check-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Schema\\Alter\\AddGeneratedColumn"):
            require(item.get("addGeneratedColumn") == [
                "main", "accounts", 1, [[0, "id"]], False, "total", "STORED", "+", 0, "7",
                'ALTER TABLE "main"."accounts" ADD COLUMN "total" INTEGER GENERATED ALWAYS AS (("id" + 7)) STORED',
            ] and item["sql"] == item["addGeneratedColumn"][-1], "installed-sqlite-add-generated-column-mismatch", item)
            require(item.get("appendedChecks") == [
                "large", 1, 1, 0, [[0, "id"], [1, "total"]],
                'ALTER TABLE "main"."accounts" ADD COLUMN "total" INTEGER GENERATED ALWAYS AS (("id" + 7)) '
                'VIRTUAL CONSTRAINT "large" CHECK (("total" > "id"))',
            ], "installed-sqlite-generated-appended-check-mismatch", item)
            require(item.get("carriedAppendedChecks") == [
                "range", "range", 'ALTER TABLE "main"."accounts" ADD COLUMN "total" GENERATED ALWAYS AS (("id" + 7)) '
                'VIRTUAL CONSTRAINT "range" CHECK (("total" > "id")) CONSTRAINT "range" CHECK (("total" < 100))',
            ], "installed-sqlite-carried-appended-check-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Schema\\Alter\\DropColumn"):
            require(item.get("dropColumn") == [
                "temp", "removable", 2, ["old", False], ["old", False],
                True, 'ALTER TABLE "temp"."removable" DROP COLUMN "old"',
            ] and item["sql"] == item["dropColumn"][-1], "installed-sqlite-drop-column-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Connection\\Attach"):
            expect = "ATTACH (?002) AS 'reports' KEY (?007)"
            require(item.get("connectionAttach") == ["002", "reports", "007", expect] and item["sql"] == expect,
                    "installed-sqlite-attach-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Connection\\Detach"):
            expect = "DETACH 'ARChive'"
            require(item.get("connectionDetach") == ["archive", "ARChive", expect, "005", "DETACH ?005"] and item["sql"] == expect,
                    "installed-sqlite-detach-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Vacuum\\Vacuum"):
            expect = 'VACUUM "archive" INTO (?002)'
            require(item.get("vacuum") == [
                "archive", "002", expect, 'VACUUM "main"', "temp", "011", 'VACUUM "temp" INTO (0 IN (?011))',
            ] and item["sql"] == expect, "installed-sqlite-vacuum-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Schema\\Trigger\\DropTrigger"):
            expect = 'DROP TRIGGER "temp"."Tracked"'
            require(item.get("dropTrigger") == ["temp", "Tracked", "archive", "watched", expect, [
                ["search-path", 'DROP TRIGGER IF EXISTS "absent"'],
                ["known-schema", 'DROP TRIGGER IF EXISTS "archive"."absent"'],
                ["unknown-schema", 'DROP TRIGGER IF EXISTS "unknown"."absent"'],
            ]] and item["sql"] == expect, "installed-sqlite-drop-trigger-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Schema\\Trigger\\CreateTrigger"):
            expected = ('CREATE TRIGGER "archive"."audit_tr" AFTER UPDATE OF "payload", "future" '
                        'ON "archive"."watched" WHEN OLD."payload" IS NOT NULL BEGIN '
                        'INSERT INTO "journal" ("prior", "current") VALUES (OLD."payload", NEW."payload"); END')
            require(item.get("auditTrigger") == ["archive", "archive", "OLD", 1, "NEW", 1, 1, "future", False, expected]
                    and item["sql"] == expected, "installed-sqlite-audit-trigger-mismatch", item)
            mutation = ('CREATE TRIGGER "archive"."alter_journal" AFTER UPDATE ON "archive"."watched" BEGIN '
                        'UPDATE OR IGNORE "journal" SET "prior" = OLD."payload", "current" = NEW."payload" '
                        'WHERE ("current" = OLD."payload"); DELETE FROM "journal" WHERE (NEW."payload" = "prior"); END')
            require(item.get("mutationTrigger") == ["archive", "ignore", 1, True, 0, False, "NEW", 1, mutation, mutation],
                    "installed-sqlite-trigger-mutation-mismatch", item)
            batch = ('CREATE TRIGGER "archive"."batch_journal" AFTER UPDATE ON "archive"."watched" BEGIN '
                     'INSERT OR FAIL INTO "journal" ("current", "prior") VALUES (NEW."payload", OLD."payload"), '
                     '(NULL, OLD."payload"); END')
            require(item.get("batchTrigger") == ["fail", [[1, 0], [1, 0]], batch, batch],
                    "installed-sqlite-trigger-batch-mismatch", item)
            interruption = ('CREATE TRIGGER "archive"."interrupt_journal" AFTER UPDATE ON "archive"."watched" '
                            'BEGIN SELECT RAISE(FAIL, OLD."payload") WHERE NEW."payload" IS NOT NULL; END')
            require(item.get("interruptTrigger") == ["FAIL", "OLD", 1, "NEW", 1, False, interruption, interruption],
                    "installed-sqlite-trigger-raise-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Cache\\CacheSize"):
            expected = 'PRAGMA "archive".cache_size(-128)'
            require(item.get("cacheSize") == ["archive", -128, expected] and item["sql"] == expected,
                    "installed-sqlite-live-cache-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Cache\\DefaultCacheSize"):
            expected = 'PRAGMA "archive".default_cache_size(2147483647)'
            require(item.get("defaultCacheSize") == ["archive", 2147483647, expected] and item["sql"] == expected,
                    "installed-sqlite-default-cache-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Cache\\CacheSpill"):
            expected = """PRAGMA "archive".cache_spill('+256')"""
            require(item.get("cacheSpill") == [
                "archive", 256, "ON", expected, 256, "OFF", 'PRAGMA "archive".cache_spill(256)',
            ] and item["sql"] == expected, "installed-sqlite-cache-spill-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Reclaim\\AutoVacuum"):
            expected = 'PRAGMA "archive".auto_vacuum(2)'
            require(item.get("autoVacuum") == [
                "archive", "2", expected, "0", 'PRAGMA "main".auto_vacuum(0)',
            ] and item["sql"] == expected, "installed-sqlite-auto-vacuum-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Reclaim\\IncrementalVacuum"):
            expected = 'PRAGMA "archive".incremental_vacuum(2)'
            require(item.get("incrementalVacuum") == [
                "archive", 2, expected, 2147483647, 'PRAGMA "temp".incremental_vacuum',
            ] and item["sql"] == expected, "installed-sqlite-incremental-vacuum-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Page\\PagePragma"):
            expect = 'PRAGMA "archive".max_page_count(4294967294)'
            require(item.get("pagePragma") == [
                "archive", "4294967294", expect, "temp", 0, 'PRAGMA "temp".page_size(0)', None, 'PRAGMA "archive".page_count',
            ] and item["sql"] == expect, "installed-sqlite-pager-pragma-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Header\\HeaderPragma"):
            expect = 'PRAGMA "metadata".user_version(-2147483648)'
            require(item.get("headerPragma") == [
                "user_version", "metadata", -2147483648, expect, "data_version", "temp", None, 'PRAGMA "temp".data_version',
            ] and item["sql"] == expect, "installed-sqlite-header-pragma-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Integrity\\IntegrityAudit"):
            expect = 'PRAGMA quick_check("integrity_child")'
            require(item.get("integrityAudit") == [
                "quick_check", "temp", "integrity_child", None, expect, 2, 'PRAGMA "main".integrity_check(2)',
                "temp", 'PRAGMA quick_check("sqlite_temp_master")',
            ] and item["sql"] == expect, "installed-sqlite-integrity-audit-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Metadata\\ForeignKeyAudit"):
            expect = 'PRAGMA "temp".foreign_key_check("audit_child")'
            require(item.get("foreignKeyAudit") == [
                "temp", "audit_child", expect, "main", 'PRAGMA "main".foreign_key_check',
                "temp", None, 'PRAGMA foreign_key_check("sqlite_temp_master")',
            ] and item["sql"] == expect, "installed-sqlite-foreign-key-audit-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Metadata\\IndexInspection"):
            expect = 'PRAGMA "main".index_xinfo("sqlite_autoindex_storage_table_2")'
            require(item.get("indexMetadata") == [
                "main", "sqlite_autoindex_storage_table_2", "storage_table", "sqlite_autoindex_storage_table_2",
                expect, "temp", "absent", 'PRAGMA "temp".index_info("absent")',
            ] and item["sql"] == expect, "installed-sqlite-index-metadata-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Metadata\\TableInspection"):
            require(item.get("tableInspection") == [
                "table_xinfo", "temp", "accounts", None, "temp", 'PRAGMA table_info("sqlite_temp_master")',
                'PRAGMA table_xinfo("accounts")',
            ] and item["sql"] == 'PRAGMA table_xinfo("accounts")', "installed-sqlite-table-inspection-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Metadata\\TableListing"):
            require(item.get("tableListing") == ["main", "sqlite_master", "", 'PRAGMA "temp".table_list("")']
                    and item["sql"] == 'PRAGMA "main".table_list("sqlite_master")', "installed-sqlite-table-listing-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Inventory\\InventoryPragma"):
            require(item.get("connectionInventory") == [
                "function_list", "temp", 'PRAGMA "temp".function_list', "pragma_list", None, "PRAGMA pragma_list",
            ] and item["sql"] == 'PRAGMA "temp".function_list', "installed-sqlite-connection-inventory-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\FlagPragma"):
            require(item.get("flagPragma") == [
                "writable_schema", "RESET", "temp", 'PRAGMA "temp".writable_schema = RESET',
                "0", "PRAGMA automatic_index = 0", None, 'PRAGMA "main".foreign_keys', True,
            ] and item["sql"] == 'PRAGMA "temp".writable_schema = RESET', "installed-sqlite-flag-pragma-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Analyze"):
            require(item.get("analyzeStatistics") == [
                "temp", "names", "accounts", "extension", 'ANALYZE "extension"', "view",
                'ANALYZE "main"."stats_view"', 'ANALYZE "temp"."names"',
            ] and item["sql"] == 'ANALYZE "temp"."names"', "installed-sqlite-analyze-statistics-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Maintenance\\Reindex"):
            require(item.get("reindex") == [
                "main", "names", "table", "NOCASE", 'REINDEX "NOCASE"', "accounts",
                'REINDEX "temp"."names"', 'REINDEX "temp"."names"',
            ] and item["sql"] == 'REINDEX "main"."names"', "installed-sqlite-reindex-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Schema\\Index\\DropIndex"):
            require(item.get("dropIndex") == [
                "temp", "tAg", "local_rows", 'DROP INDEX "temp"."tAg"', "", 'DROP INDEX IF EXISTS ""."later"',
                None, 'DROP INDEX IF EXISTS "later"', True,
            ] and item["sql"] == item["dropIndex"][3], "installed-sqlite-drop-index-mismatch", item)
            require(item.get("constraintIndexNames") == [
                True, 'DROP INDEX IF EXISTS "main"."sqlite_autoindex_known_index_3"', True,
            ], "installed-sqlite-constraint-index-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Schema\\Index\\CreateIndex"):
            require(item.get("createIndex") == [
                "temp", "indexed_tag", "indexed_rows", 1, True, True, True, ["09", "1"],
                'CREATE UNIQUE INDEX IF NOT EXISTS "temp"."indexed_tag" ON "indexed_rows" ("code" DESC) '
                'WHERE ((CASE WHEN ?09 THEN 0 WHEN ?1 THEN 0 END) NOT IN ())',
            ] and item["sql"] == item["createIndex"][-1], "installed-sqlite-create-index-mismatch", item)
            require(item.get("sqliteIndexOperandBindings") == [[1], ["code"], [1], ["code"]],
                    "installed-sqlite-index-operand-bindings", item)
            native_cast_sql = (
                'CREATE INDEX "temp"."native_cast" ON "indexed_rows" (IFNULL((CAST("id" AS "int")), 0) ASC)'
            )
            require(item.get("sqliteIndexExpressionSpelling") == [
                True, "int", "INTEGER", 0, True, native_cast_sql, native_cast_sql,
            ], "installed-sqlite-index-expression-spelling-mismatch", item)
        if (item["platform"], item["role"]) == ("Sqlite", "Schema\\Query\\CreateTableAs"):
            require(item.get("column") == ["result", "INT"], "installed-created-column-mismatch", item)
    return results, checked_roles(catalog, role_observations), role_controls(catalog, role_observations)


def digest_fault(installed, expected, directory):
    package = "sql-semantics-sqlite"
    role = "src/Ir/Select.php"
    path = installed / package / role
    original = path.read_bytes()
    modified = original + b"\n"
    path.write_bytes(modified)
    (directory / "installed-mutation.diff").write_text("".join(unified_diff(
        original.decode().splitlines(keepends=True), modified.decode().splitlines(keepends=True), f"a/{role}", f"b/{role}",
    )))
    before, after = expected[package][role], digest(path)
    require(before != after, "release-layout-fault-unapplied")
    try:
        verify_bytes(installed / package, expected[package])
    except Failure as failure:
        require(failure.signal == "installed-distribution-mismatch"
                and failure.detail == {role: {"expected": before, "actual": after}}, "release-layout-wrong-fault-detection", failure.detail)
        write_json(directory / "installed-mutation.json", {"control": "verified", "package": package, "path": role,
                   "injection": "installed-mutation.diff", "before": before, "after": after, "detected": failure.signal, "detail": failure.detail})
        return
    raise Failure("release-layout-fault-missed", role)


def checked_publication_scope(inventory):
    scope = constructor_scope({name: value for package in inventory.values() for name, value in package["classes"].items()})
    mismatches = {field: scope[field] for field in ("unregisteredConstructors", "missingConstructors",
                                                  "unexpectedPublicationRoutes", "unexpectedUnconstrainedRoutes") if scope[field]}
    require(not mismatches, "installed-constructor-ledger-mismatch", mismatches)
    return scope


def verify(candidate, tool_autoload, out, local=False):
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "release-layout-contract-mismatch")
    specification = json.loads(path.read_bytes())
    sha, manifest = candidate_distribution(candidate)
    write_json(out / "distribution.json", manifest)
    transform = checked_recipe(candidate, specification, out)
    inventory_path = out / "inventory.json"
    inventory = json.loads(command(["php", str(TOOL / "inventory.php"), str(candidate), str(tool_autoload)],
                                   out, inventory_path, XDEBUG_MODE="off"))
    publication = out / "publication-scope.json"
    write_json(publication, checked_publication_scope(inventory))
    catalog = runtime_catalog(specification, manifest, inventory)
    catalog_path = out / "runtime-catalog.json"
    write_json(catalog_path, catalog)
    report = {}
    for profile, settings in specification["profiles"].items():
        evidence = out / profile
        evidence.mkdir()
        with TemporaryDirectory(prefix="ztd-ir-package-") as temporary:
            stage = Path(temporary).resolve()
            stage.chmod(0o755)
            version = settings["version"]
            expected = stage_packages(candidate, stage, manifest, transform, settings["constraint"], version)
            command(["composer", "install", "--no-interaction", "--no-plugins", "--no-scripts", "--no-dev", "--no-progress"],
                    stage, evidence / "composer.log", COMPOSER_DISABLE_NETWORK="1", COMPOSER_HOME=str(stage / "composer-home"))
            locked = {row["name"]: row["version"] for row in json.loads((stage / "composer.lock").read_bytes())["packages"]}
            require(locked == {f"k-kinzal/{package}": version for package in PACKAGES}, "installed-versions-mismatch")
            installed = stage / "vendor/k-kinzal"
            metadata = {}
            for package, files in expected.items():
                target = installed / package
                require(target.is_dir() and not target.is_symlink(), "unexpected-package-link", package)
                verify_bytes(target, files)
                metadata[package] = digest(target / "composer.json")
                require(metadata[package] == digest(stage / "packages" / package / "composer.json"),
                        "installed-metadata-mismatch", package)
            roots, roles, controls = probe(stage, evidence, catalog, catalog_path, local)
            digest_fault(installed, expected, evidence)
            report[profile] = {"versions": locked, "packageManifests": metadata,
                               "checkedFiles": {package: len(files) for package, files in expected.items()},
                               "runtime": roots, "roles": roles, "roleControls": controls, "probeDigest": digest(evidence / "probe.log"),
                               **({"localRuntimeCatalog": digest(evidence / "local-runtime-catalog.json")} if local else {}),
                               "installedMutation": {name: digest(evidence / name) for name in ("installed-mutation.diff", "installed-mutation.json")}}
    verify_candidate(candidate, sha, manifest)
    status = "provisional-local-staged-composer-layout" if local else "provisional-staged-composer-layout"
    write_json(out / "index.json", {"status": status, "candidate": sha,
                                   "distribution": digest(out / "distribution.json"), "contract": CONTRACT,
                                   "recipe": specification["recipe"], "workerImage": None if local else IMAGE,
                                   **({"localPhp": command(["php", "--version"], out, XDEBUG_MODE="off").strip()} if local else {}),
                                   "runtimeCatalog": digest(catalog_path), "inventory": digest(inventory_path),
                                   "publicationScope": digest(publication),
                                   "tools": {name: digest(TOOL / name) for name in ("verify_release_layout.py", "release_layout_probe.php", "inventory.php", "scope.py")},
                                   "composer": command(["composer", "--version"], out).strip(), "profiles": report})


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", type=Path, required=True)
    parser.add_argument("--tool-autoload", type=Path, required=True)
    parser.add_argument("--out", type=Path, required=True)
    parser.add_argument("--local", action="store_true", help="Stage and probe in host PHP as a diagnostic.")
    args = parser.parse_args()
    candidate, out = args.candidate.resolve(), args.out.resolve()
    require(out != candidate and candidate not in out.parents, "evidence-directory-inside-candidate")
    out.mkdir(parents=True, exist_ok=False)
    try:
        verify(candidate, args.tool_autoload.resolve(), out, args.local)
    except Exception as error:
        write_json(out / "run-failure.json", {"signal": error.signal if isinstance(error, Failure) else type(error).__name__,
                                             "failure": str(error)})
        raise
