"""Recompute PostgreSQL built-in cast comparisons from retained observations."""

import run_server_postgres_builtin_casts
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_builtin_casts)
