"""Replay both pinned native PostgreSQL int4 operator profiles."""

import run_server_postgres_int4
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_int4)
