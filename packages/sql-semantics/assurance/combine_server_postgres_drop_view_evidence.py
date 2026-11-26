"""Independently replay PostgreSQL view removal and transaction evidence."""

import run_server_postgres_drop_view
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_drop_view)
