"""Replay PostgreSQL storage placement against the exact release artifacts."""

import run_server_postgres_reindex_tablespace
from combine_server_join_evidence import main

if __name__ == "__main__":
    main(run_server_postgres_reindex_tablespace)
