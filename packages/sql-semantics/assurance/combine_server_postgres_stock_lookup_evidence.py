"""Independently replay PostgreSQL stock and shadowed lookup evidence."""

from combine_server_join_evidence import main
import run_server_postgres_stock_lookup

if __name__ == "__main__":
    main(run_server_postgres_stock_lookup)
