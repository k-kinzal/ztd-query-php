"""Independently replay the MySQL explicit alias receipts."""

from combine_server_join_evidence import main
import run_server_mysql_signed_projection_alias


if __name__ == "__main__":
    main(run_server_mysql_signed_projection_alias)
