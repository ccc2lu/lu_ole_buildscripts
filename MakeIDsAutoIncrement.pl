#! /usr/bin/perl

#use Term::ReadKey;

# Author: Chris Creswell (ccc2@lehigh.edu)
# Updated: 5/7/2014
# This script modifies a bunch of ID fields for tables in OLE's
# database to be auto-incrementing fields in MySQL.  
# This was necessary to do before running the JPA-based Java
# program for populating catalog data that I wrote.  The JPA MySQL
# driver assumed that ID fields had to be auto_increment.  OLE
# instead used sequence tables, which are needed to support Oracle.
# It was easiest to just make the ID fields auto_increment and then
# sync up the sequence tables later, which is what 
# SyncSequencesWithIDs.pl is for.

print "Modifying IDs ...\n";
#print "Enter the MySQL root password: ";
#ReadMode('noecho');
#$pw = <>;
#ReadMode(0);
#chomp($pw);
#$username = "root";
$username = "OLE_DB_USER";
$pw = "OLE_DB_USER_PASSWORD";
print "\n";
$tables = `echo "show tables;" | mysql --user=$username --password=$pw ole`;
$tables = `echo -e "$tables\nole_cat_itm_typ_t\nole_dlvr_item_avail_stat_t\nole_cat_type_ownership_t\nole_ser_rcv_rec\nole_ser_rcv_his_rec\nole_ser_rcv_rec_typ_t\n"`;
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
    "krns_doc_hdr_t" => "DOC_HDR_ID",
    "krew_doc_hdr_cntnt_t" => "DOC_HDR_ID"
    );

%fkeytables = (
    "FK_SER_TPY_ID" => "ole_ser_rcv_rec_typ_t",
    "FK_SER_ID" => "ole_ser_rcv_his_rec"
    );
%fkeyqueries = (
    "FK_SER_TPY_ID" => 'alter table ole_ser_rcv_rec_typ_t add constraint FK_SER_TYP_ID foreign key (SER_RCV_REC_ID) references ole_ser_rcv_rec (SER_RCV_REC_ID);',
    "FK_SER_ID" => 'alter table ole_ser_rcv_his_rec add constraint FK_SER_ID foreign key (SER_RCV_REC_ID) references ole_ser_rcv_rec (SER_RCV_REC_ID);'
    );

print "Removing foreign keys ...\n";
for $fkey ( keys %fkeytables ) {
    $query = "alter table " . $fkeytables{$fkey} . " drop foreign key " . $fkey . ";";
    print "Query: $query\n";
    `echo "$query;" | mysql --user=$username --password=$pw ole`;
}

foreach $table ( split("\n", $tables) ) {
    if ( $table =~ m/ole_ds_(.*)_t$/ || $idfields{$table} ) {
	if ( $idfields{$table} ) {
	    $idfield = $idfields{$table};
	} else {
	    $idfield = uc($1) . "_ID"; # This works for most of the tables
	}
	print "Modifying $idfield of $table\n";
	$query = "alter table $table modify $idfield int(11) auto_increment";
	print "Query: $query\n";
	`echo '$query;' | mysql --user=$username --password=$pw ole`;
    }
}

#print "Putting back foreign keys ...\n";
#for $fkey ( keys %fkeyqueries ) {
#    $query = $fkeyqueries{$fkey};
#    print "Query: $query\n";
#    `echo \"$query;\" | mysql --user=$username --password=$pw ole`;
#}
