"""Independently replay native PostgreSQL signed write-width evidence."""

import run_server_postgres_signed_write_widths
from combine_server_join_evidence import main


if __name__ == "__main__":
    main(run_server_postgres_signed_write_widths)
