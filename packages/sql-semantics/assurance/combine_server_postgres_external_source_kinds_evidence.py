"""Recompute external PostgreSQL relation observations on each pinned release."""

import run_server_postgres_external_source_kinds
from combine_server_join_evidence import main

if __name__ == "__main__":
    main(run_server_postgres_external_source_kinds)
