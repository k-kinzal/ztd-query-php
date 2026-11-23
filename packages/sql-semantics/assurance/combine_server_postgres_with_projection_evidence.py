"""Independently replay ordinary PostgreSQL WITH evidence."""

import run_server_postgres_with_projection
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_with_projection)
