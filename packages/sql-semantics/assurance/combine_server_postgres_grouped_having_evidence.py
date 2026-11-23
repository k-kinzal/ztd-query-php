"""Independently replay each pinned PostgreSQL HAVING report."""

import run_server_postgres_grouped_having
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_grouped_having)
