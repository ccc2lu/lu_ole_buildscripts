#!/bin/bash

# Author: Chris Creswell (ccc2@lehigh.edu)
# Updated: 5/7/2014 
# This script takes a .sql export and a .csv formatted export of the
# OLE serials tables as they are, then sets several fields to NULL to
# create skeleton records, then takes exports of the tables again
# in that state, then truncates the tables and resets the sequences.
# This is all because the tables are populated by a Java program that
# loads other catalog data, but it turns out RICE tables must also be 
# populated for serials data (unlike other catalog data) in order for 
# OLE to work.

# So the already converted data has to be run through a batch process in
# OLE that was written by HTC to create that RICE paper-trail for each
# serial record.  HTC's batch process doesn't handle 
# special characters like comma or double-quote in certain fields of the
# data though, hence the creation of the skeleton records.  After the
# batch process is run, the script fixserials.php can be used to
# put the data back into the fields that were set to NULL using the
# exports created by this script.

if [ -z "$serialsexportdir" ] ; then
    export serialsexportdir=`pwd`/serialsexport
fi

user=OLE_DB_USER
password=OLE_DB_USER_PASSWORD
dbname=ole

mkdir -p ${serialsexportdir}/full_load/sql
mkdir -p ${serialsexportdir}/full_load/csv
mkdir -p ${serialsexportdir}/skeleton_recs/sql
mkdir -p ${serialsexportdir}/skeleton_recs/csv

tables=`echo -e "ole_ser_rcv_rec\nole_ser_rcv_rec_typ_t\nole_ser_rcv_his_rec\n"`

# Dump the full records, first to SQL
echo "Dumping skeletal tables in .sql format to ${serialsexportdir}/skeleton_recs/sql/"
for table in $tables
do
    mysqldump -u $user -p${password} $dbname $table > ${serialsexportdir}/full_load/sql/${table}.sql    
done

# Then to CSV
export csvdir=${serialsexportdir}/full_load/csv
chmod 777 ${csvdir}
tempfile=${csvdir}/tempfile.csv
echo "Dumping full serials receiving tables to csv files in $csvdir"
for table in $tables
do
    number=`echo "select count(*) from $table;" | mysql -u $user -p${password} $dbname | tail -n 1`
    if [ $number -ne 0 ] ; then
	echo "$number rows in $table, dumping to csv ..."

	FNAME=${csvdir}/${table}.csv

	# This cluge of a method gets us the CSV file we want WITH column header information at the top:
	mysql -u $user -p${password} $dbname -B -e "SELECT COLUMN_NAME FROM information_schema.COLUMNS C WHERE table_name = '$table';" | awk '{print $1}' | grep -iv ^COLUMN_NAME$ | sed 's/^/"/g;s/$/"/g' | tr '\n' ',' > $FNAME

        # Append a newline after the column headers
	echo "" >> $FNAME

        # Dump the data into $tempfile
	mysql -u $user -p${password} $dbname -B -e "SELECT * INTO OUTFILE '$tempfile' FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' FROM $table;"

	# Replace \N output by MySQL for NULL values with empty double quotes
	sed -i 's/\\N/""/g' $tempfile

        # Merge the data file and file w/ column names
	cat $tempfile >> $FNAME

        # deletes tempfile
	sudo rm $tempfile

	# The "select into outfile" method:
	#outfile="/tmp/${table}.csv"
	# query="select * into OUTFILE '${outfile}' fields terminated by ',' enclosed by '\"' lines terminated by '\n' from $table"
	# echo "Query $query"
	# `echo "$query" | mysql -u $user -p${password} $dbname`

	# The mysqldump method:
	#outdir="/tmp"
	#`mysqldump --complete-insert -u $user -p${password} -t -T${outdir} $dbname $table --fields-terminated-by=',' --fields-enclosed-by='"'`
    else
	echo "No rows in $table, NOT dumping ..."
    fi
done

# Then null out some fields
echo "Setting non-essential fields to NULL"
echo "update ole_ser_rcv_rec set fdoc_nbr=NULL" | mysql -u $user -p${password} $dbname
echo "update ole_ser_rcv_rec set gen_rcv_note=NULL" | mysql -u $user -p${password} $dbname
echo "update ole_ser_rcv_rec set treatment_instr_note=NULL" | mysql -u $user -p${password} $dbname
echo "update ole_ser_rcv_rec set unbound_loc=NULL" | mysql -u $user -p${password} $dbname

# Then dump again to the skeleton_recs folder, first to SQL
echo "Dumping skeletal tables in .sql format to ${serialsexportdir}/skeleton_recs/sql/"
for table in $tables
do
    mysqldump -u $user -p${password} $dbname $table > ${serialsexportdir}/skeleton_recs/sql/${table}.sql    
done

# Then to CSV
export csvdir=${serialsexportdir}/skeleton_recs/csv
chmod 777 ${csvdir}
tempfile=${csvdir}/tempfile.csv
echo "Dumping skeletal serials receiving tables to csv files in $csvdir"
for table in $tables
do
    number=`echo "select count(*) from $table;" | mysql -u $user -p${password} $dbname | tail -n 1`
    if [ $number -ne 0 ] ; then
	echo "$number rows in $table, dumping to csv ..."

	FNAME=${csvdir}/${table}.csv

	# This cluge of a method gets us the CSV file we want WITH column header information at the top:
        # creates empty file and sets up column names using the information_schema
	mysql -u $user -p${password} $dbname -B -e "SELECT COLUMN_NAME FROM information_schema.COLUMNS C WHERE table_name = '$table';" | awk '{print $1}' | grep -iv ^COLUMN_NAME$ | sed 's/^/"/g;s/$/"/g' | tr '\n' ',' > $FNAME

        # appends newline to mark beginning of data vs. column titles
	echo "" >> $FNAME

        # dump data from DB into $tempfile
	mysql -u $user -p${password} $dbname -B -e "SELECT * INTO OUTFILE '$tempfile' FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' FROM $table;"

	# Replace \N output by MySQL for NULL values with empty double quotes
	sed -i 's/\\N/""/g' $tempfile

        # merges data file and file w/ column names
	cat $tempfile >> $FNAME

        # deletes tempfile
	sudo rm $tempfile

	# The "select into outfile" method:
	#outfile="/tmp/${table}.csv"
	# query="select * into OUTFILE '${outfile}' fields terminated by ',' enclosed by '\"' lines terminated by '\n' from $table"
	# echo "Query $query"
	# `echo "$query" | mysql -u $user -p${password} $dbname`

	# The mysqldump method:
	#outdir="/tmp"
	#`mysqldump --complete-insert -u $user -p${password} -t -T${outdir} $dbname $table --fields-terminated-by=',' --fields-enclosed-by='"'`
    else
	echo "No rows in $table, NOT dumping ..."
    fi
done

# Then delete everything from these tables since HTC's loader will be
# recreating it all
echo "Clearing out serials receiving tables"
for table in $tables
do
    echo "delete from $table" | mysql -u $user -p${password} $dbname
done

echo "Resetting sequences and auto_increment values"
echo "alter table ole_ser_rcv_his_rec auto_increment=1;" | mysql -u $user -p${password} $dbname
echo "alter table ole_ser_rcv_rec_typ_t auto_increment=1;" | mysql -u $user -p${password} $dbname
echo "alter table ole_ser_rcv_rec auto_increment=1;" | mysql -u $user -p${password} $dbname
echo "delete from ole_ser_rcv_seq;" | mysql -u $user -p${password} $dbname
echo "delete from ole_ser_rcv_his_seq;" | mysql -u $user -p${password} $dbname
echo "alter table ole_ser_rcv_seq auto_increment=1;" | mysql -u $user -p${password} $dbname
echo "alter table ole_ser_rcv_his_seq auto_increment=1;" | mysql -u $user -p${password} $dbname

