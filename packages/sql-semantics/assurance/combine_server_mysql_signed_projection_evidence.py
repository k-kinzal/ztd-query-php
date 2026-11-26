"""Independently replay native signed projection receipts across all required releases."""

from combine_server_join_evidence import main
import run_server_mysql_signed_projection


if __name__ == "__main__":
    main(run_server_mysql_signed_projection)
