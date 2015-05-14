#! /usr/bin/perl

# Author: Chris Creswell (ccc2@lehigh.edu)
# Updated: 5/7/2014
# This script needs to be run after the various tools
# that do direct database inserts have all finished.
# It syncs up the sequence generator tables that OLE
# itself uses with the max IDs of the primary key 
# fields that were created by the data loading scripts.

#use Term::ReadKey;
use DBI;
use DBD::mysql;

print STDERR "Modifying IDs ...\n";
#print "Enter the MySQL root password: ";
#ReadMode('noecho');
#$pw = <>;
#ReadMode(0);
#chomp($pw);
#$username = "root";
$username = "OLE_DB_USER";
$host = "localhost";
$dbname = "ole";
$pw = "OLE_DB_USER_PASSWORD";

$connectionInfo = "dbi:mysql:database=$dbname";
$connection = DBI->connect($connectionInfo, $username, $pw);

print STDERR "\n";
$tables = `echo "show tables;" | mysql --user=$username --password=$pw ole`;
#$tables = `echo -e "$tables\nole_cat_itm_typ_t\nole_dlvr_item_avail_stat_t\nole_cat_type_ownership_t\nole_ser_rcv_rec\nole_ser_rcv_his_rec\nole_ser_rcv_rec_typ_t\n"`;
#print "Tables: $tables";
%idfields = ( 
    "ole_ds_coverage_t" => "EHOLDINGS_COVERAGE_ID",
    "ole_ds_holdings_access_uri_t" => "ACCESS_URI_ID",
    "ole_ds_loc_checkin_count_t" => "CHECK_IN_LOCATION_ID",
    "ole_ds_perpetual_access_t" => "HOLDINGS_PERPETUAL_ACCESS_ID",
    "ole_ds_authentication_t" => "AUTHENTICATION_TYPE_ID",
    "ole_ds_holdings_access_loc_t" => "HOLDINGS_ACCESS_LOCATION_ID",
    "ole_ds_search_facet_size_t" => "DOC_SEARCH_FACET_SIZE_ID",
    "ole_ds_itm_former_identifier_t" => "ITEM_FORMER_IDENTIFIER_ID",
    "ole_ds_search_result_page_t" => "DOC_SEARCH_PAGE_SIZE_ID",
     # Tables outside the docstore:
    "ole_cat_itm_typ_t" => "ITM_TYP_CD_ID",
    "ole_dlvr_item_avail_stat_t" => "ITEM_AVAIL_STAT_ID",
    "ole_cat_type_ownership_t" => "TYPE_OWNERSHIP_ID",
    "ole_ser_rcv_rec" => "SER_RCV_REC_ID",
    "ole_ser_rcv_rec_typ_t" => "SER_RCV_REC_TYP_ID",
    "ole_ser_rcv_his_rec" => "SER_RCPT_HIS_REC_ID",
    "krew_doc_hdr_t" => "DOC_HDR_ID",
    # From loadaccounts.php: Shouldn't be necessary -- this is loaded to csv now
    # ca_account_t, 
    # From loadbills:
    "ole_dlvr_ptrn_bill_t" => "PTRN_BILL_ID",
    "ole_dlvr_ptrn_bill_fee_typ_t" => "ID",
    # From loadcharges.php:
    "ole_dlvr_loan_t" => "LOAN_TRAN_ID",
    # From loadholds.php:
    "ole_dlvr_rqst_t" => "OLE_RQST_ID",
    # From loadpatrons.php:
    "krim_entity_t" => "ENTITY_ID",
    #"krim_entity_ent_typ_t" => "ENT_TYP_CD", # No sequence for this one
    "krim_entity_nm_t" => "ENTITY_NM_ID",
    "krim_entity_email_t" => "ENTITY_EMAIL_ID",
    "krim_entity_addr_t" => "ENTITY_ADDR_ID",
    "ole_ptrn_t" => "OLE_PTRN_ID",
    "ole_dlvr_add_t" => "DLVR_ADD_ID",
    # From loadvendors:
    #"pur_vndr_hdr_t" => "VNDR_HDR_GNRTD_ID", # No sequences for these commented out ones
    #"pur_vndr_dtl_t" => "VNDR_DTL_ASND_ID",
    "pur_vndr_alias_t" => "OLE_PUR_VNDR_ALIAS_ID"
    );

%sequencenames = (
    "krim_entity_t" => "krim_entity_id_s",
    "krim_entity_nm_t" => "krim_entity_nm_id_s",
    "krim_entity_email_t" => "krim_entity_email_id_s",
    "krim_entity_addr_t" => "krim_entity_addr_id_s",
    "pur_vndr_alias_t" => "ole_pur_vndr_alias_id_seq",
    "ole_ser_rcv_his_rec" => "ole_ser_rcv_his_seq",
    "ole_ser_rcv_rec" => "ole_ser_rcv_seq",
    "krew_doc_hdr_t" => "krew_doc_hdr_s",
    # Entries with value "N/A" means there is no sequence (that I could find)
    # for generating their ID that we need to set
    "ole_ds_access_location_code_t" => "N/A",
    "ole_ptrn_t" => "N/A"
    );

sub getID 
{
    my $table = shift;
    my $idfield = shift;
    my $query = "select max(cast($idfield as unsigned)) from $table";
    my $statement = $connection->prepare($query);
    $statement->execute();
    @data = $statement->fetchrow_array();
    return $data[0] + 1;
}

sub getSequenceName
{
    my $table = shift;
    my $sequence = $table;
    if ( $sequencenames{$table} ) {
	$sequence = $sequencenames{$table};
    } else {
	$sequence =~ s/(.+)_t$/$1_s/;
	my $query = "show tables like '$sequence'";
	my $statement = $connection->prepare($query);
	$statement->execute();
	@data = $statement->fetchrow_array();
	if ( !(scalar(@data)) ) { 
	    # Nt table found that's just tablename_s instead of tablename_t, try tablename_id_s
	    $sequence = $table;
	    $sequence =~ s/(.+)_t$/$1_id_s/;
	    $query = "show tables like '$sequence'";
	    $statement = $connection->prepare($query);
	    $statement->execute();
	    @data = $statement->fetchrow_array();
	    if ( !(scalar(@data)) ) {
		$sequence = $table;
		$sequence =~ s/(.+)_t$/$1_id_seq/;
		$query = "show tables like '$sequence'";
		$statement = $connection->prepare($query);
		$statement->execute();
		@data = $statement->fetchrow_array();	    
		if ( !(scalar(@data)) ) {
		    $sequence = "N/A";
		}
	    }
	}
    }
    return $sequence;
}

sub setSequence
{
    my $table = shift;
    my $id = shift;
    my $sequence = &getSequenceName($table);   
    print STDERR "Sequence name for $table is $sequence\n";
    if ( $sequence ne "N/A" ) {
	my $query = "insert into $sequence (id) values ($id)";
	print STDERR "Setting sequence query: $query\n";
	my $statement = $connection->prepare($query);
	$statement->execute();
    } else {
	print STDERR "Skipping table $table, sequence is N/A\n";
    }
}

foreach $table ( split("\n", $tables) ) {
    if ( $table =~ m/ole_ds_(.*)_t$/ || $idfields{$table} ) {
	if ( $idfields{$table} ) {
	    $idfield = $idfields{$table};
	} else {
	    $idfield = uc($1) . "_ID"; # This works for most of the tables
	}
	print STDERR "Updating sequence for $idfield of $table\n";
	$nextId = &getID($table, $idfield);
	&setSequence($table, $nextId);
    }
}

