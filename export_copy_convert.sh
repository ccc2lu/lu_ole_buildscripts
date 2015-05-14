#!/bin/bash                                                                                                                                                                                   

# Author: Chris Creswell (ccc2@lehigh.edu)
# Updated: 5/7/2014 
# This script runs a series of export commands using
# SirsiDynix Symphony's "sel" tools.  It then copies
# the resulting files back to a directory on the machine
# running the script, converts the data to MarcXML using
# marc4j, prunes some bad character data from the resulting 
# XML and base64 encodes some invalid XML character references
# using a perl script I wrote.

# Recursively fetches all the files from $remotedir to $localdir

function fetchfiles
{
    user=$1
    server=$2
    remotedir=$3
    localdir=$4
    /usr/bin/sftp $user@$server <<EOF>/dev/null
                cd ..
                cd ..
                cd $remotedir
                lcd $localdir
                get -r *
                quit
 
EOF
}
if [ -z "$datestamp" ] ; then
    datestamp=`date +%Y%m%d`
fi
if [ -z "$user" ] ; then
    user="sirsi"
fi
if [ -z "$server" ] ; then
    server="sirsi.server.hostname.here"    
fi
if [ -z "$remotedest" ] ; then
    #remotedest="/ExtDisk/$datestamp"
    remotedest="/sirsi/s/sirsi/Unicorn/Xfer/$datestamp"
fi
if [ -z "$sirsiexportdir" ] ; then
    sirsiexportdir="/usr/local/oledev/sirsidump/$datestamp"
fi

mkdir -p $sirsiexportdir
ssh $user@$server "mkdir -p $remotedest"

echo "Dumping whole catalog to $remotedest on $server"
# We pipe the command to ssh so that we can use bash on the other end without
# changing the user's shell (which is crappy csh), so we can use bash's 
# ability to redirect stdout and stderr to different places.
echo "/sirsi/s/sirsi/Unicorn/dumpallsirsirecords.sh $remotedest/ > $remotedest/fullcatalogdump.log 2>&1" | ssh $user@$server '/usr/local/bin/bash -s'
 # ssh $user@$server "/sirsi/s/sirsi/Unicorn/dumpallsirsirecords.sh $remotedest/ >&! $remotedest/fullcatalogdump.log"

# The above dump comes out in MARC format and therefore doesn't include
# the shadowed value for records, or their "status", or their 
# creation, cataloged, and modified dates.  It also doesn't include
# the "flexible" key, which is the title control number our librarians need
# to have in OLE in an 035 field.  So, we ouput those separately
# alongside the catalog keys with the following command.
# This is joined with the MARC data later by the Java conversion tool.
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selcatalog -n\">0\" -z\">0\" -iS -e008 -oCe6upqrF > $remotedest/catalog-all.KeysAndDates 2> $remotedest/KeysAndDatesDump.log" | ssh $user@$server '/usr/local/bin/bash -s'

# Here we don't care about redirecting stderr to a different place, but we do want to set UPATH first
echo "Dumping catalog data ..."
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selitem -oKabcdfhjlmnpqrstuvwyzA1234567Bk > $remotedest/allitems.txt" | ssh $user@$server '/usr/local/bin/bash -s'
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selcallnum -iS -oKabchpqryz2 > $remotedest/allcallnums.txt" | ssh $user@$server '/usr/local/bin/bash -s'
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selcallnum -iS -oKZ > $remotedest/allcallnumsanalytics.txt" | ssh $user@$server '/usr/local/bin/bash -s'
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selcallnum -iS -oKD > $remotedest/allcallnumsitemnumbers.txt" | ssh $user@$server '/usr/local/bin/bash -s'
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selcallnum -iS -oKA > $remotedest/allcallnumsshelvingkeys.txt" | ssh $user@$server '/usr/local/bin/bash -s'
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selbound -oKPcdy > $remotedest/boundwiths.txt" | ssh $user@$server '/usr/local/bin/bash -s'
echo "Dumping patron and financial data ..."
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selhold -jACTIVE | /sirsi/s/sirsi/Unicorn/Bin/dumpflathold > $remotedest/holds.data" | ssh $user@$server '/usr/local/bin/bash -s'
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selcharge -r=0 | /sirsi/s/sirsi/Unicorn/Bin/dumpflatcharge  > $remotedest/charge.norenewals.data" | ssh $user@$server '/usr/local/bin/bash -s'
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selcharge -r'>0' | /sirsi/s/sirsi/Unicorn/Bin/dumpflatcharge  > $remotedest/charge.renewals.data" | ssh $user@$server '/usr/local/bin/bash -s'
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/seluser | /sirsi/s/sirsi/Unicorn/Bin/dumpflatuser  > $remotedest/users.data" | ssh $user@$server '/usr/local/bin/bash -s'
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/seluser -oBK > $remotedest/lins.data" | ssh $user@$server '/usr/local/bin/bash -s'
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selvendor | /sirsi/s/sirsi/Unicorn/Bin/dumpflatvendor  > $remotedest/vendor.data" | ssh $user@$server '/usr/local/bin/bash -s'
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selfund | /sirsi/s/sirsi/Unicorn/Bin/dumpflatfund  > $remotedest/fund.data" | ssh $user@$server '/usr/local/bin/bash -s'
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selbill | /sirsi/s/sirsi/Unicorn/Bin/dumpflatbill  > $remotedest/bill.data" | ssh $user@$server '/usr/local/bin/bash -s'
echo "Dumping serials data ..."
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selprediction | /sirsi/s/sirsi/Unicorn/Bin/dumpflatissue > $remotedest/serissues.data 2> $remotedest/serissues.errors" | ssh $user@$server '/usr/local/bin/bash -s'
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selserctl | /sirsi/s/sirsi/Unicorn/Bin/dumpflatserctl > $remotedest/serctl.data 2> $remotedest/serctl.errors" | ssh $user@$server '/usr/local/bin/bash -s'
echo "export UPATH=/s/sirsi/Unicorn/Config/upath; /sirsi/s/sirsi/Unicorn/Bin/selorder -tSUBSCRIPT,STANDING -oK -3=0 | /sirsi/s/sirsi/Unicorn/Bin/dumpflatorder > $remotedest/order.data 2> $remotedest/order.errors" | ssh $user@$server '/usr/local/bin/bash -s'

echo "Copying sirsi export files to local folder $sirsiexportdir"
# Copy the resulting files back
fetchfiles $user $server $remotedest $sirsiexportdir

# Clean up after all the exports, since disk space is 
# always low on our sirsi server 
# It's too dangerous to script an rm *, but
# I can name the files to be deleted and remove them
# each explicitly.
# This first set of files is within the nested datestamped folder that catalogdump creates
dumpfiles=( "catalog.01.mrc" "catalog.defaultkey.mrc" "catalogdump-translated.log" "remaining" "catalog-all.keys" "selcatalog.log" "catalogdump.log" "catalog.keys" "working" )
for dumpfile in "${dumpfiles[@]}"
do
    echo "Removing $remotedest/$datestamp/$dumpfile on $server"
    ssh $user@$server "rm $remotedest/$datestamp/$dumpfile"
done
# Folder should be empty now
ssh $user@$server "rmdir $remotedest/$datestamp"
# Then this next set of folders is up a level, just within $remotedest
dumpfiles=( "allitems.txt" "boundwiths.txt" "allcallnumsanalytics.txt" "allcallnumsitemnumbers.txt" "allcallnumsshelvingkeys.txt" "allcallnums.txt" "catalog-all.KeysAndDates" "fullcatalogdump.log" "KeysAndDatesDump.log" "holds.data" "charge.norenewals.data" "charge.renewals.data" "users.data" "lins.data" "vendor.data" "fund.data" "bill.data" "serissues.data" "serissues.errors" "serctl.data" "serctl.errors" "order.data" "order.errors" )
for dumpfile in "${dumpfiles[@]}"
do
    echo "Removing $remotedest/$dumpfile on $server"
    ssh $user@$server "rm $remotedest/$dumpfile"
done
ssh $user@$server "rmdir $remotedest"

# All cleaned up.  Now we begin converting stuff to OLE ingest format

if [ -z "$tools_dir" ] ; then
    tools_dir=/usr/local/oledev/buildscripts
fi

# Now convert the catalog to MarcXML, and make a first50000 records version too
echo "Converting catalog data to MarcXML"
java -jar $tools_dir/LU_xml_bib_convert.jar $sirsiexportdir/$datestamp/catalog.01.mrc $sirsiexportdir/catalog.marcxml
# The "first50000" file is convenient because it's not too big to be opened in an editor
# This is helpful for finding conversion problems
java -jar $tools_dir/LU_xml_bib_convert.jar $sirsiexportdir/$datestamp/catalog.01.mrc $sirsiexportdir/catalog.first50000.marcxml 50000

# Now get rid of the invalid XML character references in the MarcXML, and
# replace LATIN1 encoded 0xbe characters in allcallnumsitemnumbers with
# <U+00be>, which is what marc4j does with them
#$tools_dir/fixdata.pl $sirsiexportdir/catalog.marcxml > $sirsiexportdir/catalog.mod.marcxml 2> $sirsiexportdir/fixdata.out
echo "Replacing or encoding bad character data in MarcXML files"
$tools_dir/fixdata.pl $sirsiexportdir catalog.marcxml mod 2> $sirsiexportdir/fixdata.out
$tools_dir/fixdata.pl $sirsiexportdir catalog.first50000.marcxml mod 2> $sirsiexportdir/fixdata.first50000.out
