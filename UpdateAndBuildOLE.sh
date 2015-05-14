#!/bin/bash
clear

# Author: Chris Creswell (ccc2@lehigh.edu)
# Updated: 5/7/2014
# This script builds the OLE application from the source
# tree that is under $OLE_DEVELOPMENT_WORKSPACE_ROOT.
# There are a bunch of environment variables that govern its
# behavior, which can be set by running the setupbuild.sh script.
# That script must be sourced for the variable settings it
# performs to take effect, so run it like so:
# . setupbuild.sh

if [ -z "$demodata" ] ; then
    demodata="N"
fi

if [ -z "$update" ] ; then
    update="N"
fi

if [ -z "$builddir" ] ; then
    echo "You must define the \"builddir\" environment variable, e. g."
    echo "export builddir=\"/usr/local/oledev/buildscripts\""
    exit
fi

if [ -z "$OLE_DEVELOPMENT_WORKSPACE_ROOT" ] ; then
    echo "You must define the \"OLE_DEVELOPMENT_WORKSPACE_ROOT\" environment variable, e. g."
    echo "export OLE_DEVELOPMENT_WORKSPACE_ROOT=\"/usr/local/oledev/tst/trunk\""
    exit
fi

read dbpassword < dbpw.txt

export tools_dir="$builddir"

buildtype="inst"
#buildtype="bootstrap"
inst_data_dir=${builddir}/ole-inst

basedir=`pwd`
loadscriptsdir="${builddir}/loadscripts"
if [ -z "$migrationdbdir" ] ; then
    migrationdbdir="$loadscriptsdir"
fi

export TOMCAT_VER=tomcat7
export TOMCAT_HOME=/usr/share/${TOMCAT_VER}
export TOMCAT_DEPLOY=/var/lib/${TOMCAT_VER}
export TOMCAT_LOG=/var/log/${TOMCAT_VER}

echo "Cleaning up old files from $HOME, tomcat user's home in ${TOMCAT_HOME}, tomcat deployed stuff under ${TOMCAT_DEPLOY}, and log files"

echo ${OLE_DEVELOPMENT_WORKSPACE_ROOT} 

if [ -z "$datestamp" ] ; then
    datestamp=`date +%Y%m%d`
fi
if [ -z "$tools_dir" ] ; then
    export tools_dir="$builddir"
fi
if [ -z "$ludatadir" ] ; then
    ludatadir="$tools_dir/LehighData"
fi
if [ -z "$sirsiexportdir" ] ; then
    export sirsiexportdir="$tools_dir/sirsidump/$datestamp"
fi
if [ -z "$new_export" ] ; then
    new_export="Y"
fi
if [ -z "$stage_export" ] ; then
    stage_export="Y"
fi
if [ -z "$stage_loader_data" ] ; then
    stage_loader_data="$stage_export"
fi
if [ -z "$init_db" ] ; then
    init_db="Y"
fi
if [ -z "$oledbhost" ] ; then
    oledbhost="localhost"
fi
if [ -z "$docstore_url" ] ; then
    docstore_url="http://${oledbhost}/oledocstore"
fi

if [ "$update" == "Y" ] ; then
    echo "Updating from SVN"
    svn update
else 
    echo "NOT updating from SVN"
fi

# Now put Lehigh data in place of demo data
#rm ${OLE_DEVELOPMENT_WORKSPACE_ROOT}/ole-app/olefs/src/main/resources/org/kuali/ole/describe/defaultload/*.xml
#cp ~/dev/ole/LehighData/Lehigh Locations.xml ${OLE_DEVELOPMENT_WORKSPACE_ROOT}/ole-app/olefs/src/main/resources/org/kuali/ole/describe/defaultload/ 

if [ "$demodata" == "N" ] ; then
    
    if [ "$buildtype" == "inst" ] ; then

	# Dump data from Sirsi
	if [ "$new_export" == "Y" ] ; then
	    echo "Calling script to export data from Sirsi and putting it in $sirsiexportdir"
	    ${builddir}/export_copy_convert.sh
	else
	    echo "Not exporting new data from Sirsi, using existing data in $sirsiexportdir"
	fi

	if [ "$stage_export" == "Y" ] ; then
	    echo "Staging data in olemigration database"
	    cd ${loadscriptsdir}
	    echo "Staging accounts"
	    php stageaccounts.php  
	    echo "Staging charges"
	    php stagecharges.php 
	    echo "Staging holds" 
	    php stageholds.php 
	    echo "Staging patrons" 
	    php stagepatrons.php 
	    echo "Staging roles" 
	    ${loadscriptsdir}/stageroles.pl
	    echo "Staging vendors" 
	    php stagevendors.php

	    echo "Stating serial control records"
	    php stageserials.php
	    php getbibsforserials.php
	    echo "Staging serial issue records"
	    php stageserialsissues.php
	else
	    echo "Not refreshing olemigration database with exported data"
	fi

	cd ${builddir}
	
	echo "Creating dynamically created .CSV files"
	cd ${loadscriptsdir}
	#php loadaccountstocsv.php
	#echo
	php loadvendorstocsv.php
	echo

        # For building with institutional data to be loaded from .CSV files:
	echo "Copying .CSV files to the ole-inst folder"
	cp -r ${inst_data_dir}/* ${OLE_DEVELOPMENT_WORKSPACE_ROOT}/ole-app/ole-db/ole-liquibase/ole-liquibase-changeset/src/main/resources/ole-inst

	echo "Building all of OLE"
	cd ${OLE_DEVELOPMENT_WORKSPACE_ROOT}
	mvn clean install -DskipTests=true

        # Don't want to do this part when building with bootstrap sql only
	echo "Loading inst data"
	echo
	echo "Building ole-app/ole-db/ole-impex"
	cd ${OLE_DEVELOPMENT_WORKSPACE_ROOT}/ole-app/ole-db/ole-impex
	# For institutions:
	mvn clean install -Pinst-sql -Dimpex.scm.phase=none 
	# For developers (Jon Miller uses this one):
	#mvn clean install -Psql -Dimpex.scm.phase=none
	
	echo
	echo "Building ole-app/ole-db/ole-liquibase/ole-liquibase-changeset"
	cd ${OLE_DEVELOPMENT_WORKSPACE_ROOT}/ole-app/ole-db/ole-liquibase/ole-liquibase-changeset
	mvn clean install -Pinst-mysql,mysql -Dimpex.scm.phase=none
        #mvn clean install -Pinst-sql,oracle 

    elif [ "$buildtype" == "bootstrap" ] ; then

	echo "Building all of OLE"
	cd ${OLE_DEVELOPMENT_WORKSPACE_ROOT}
	mvn clean install -DskipTests=true

	echo
	echo "Building ole-app/ole-db/ole-liquibase/ole-liquibase-changeset"
	cd ${OLE_DEVELOPMENT_WORKSPACE_ROOT}/ole-app/ole-db/ole-liquibase/ole-liquibase-changeset

	mvn clean install -Pbootstrap-sql-only,mysql 
        #mvn clean install -Pbootstrap-sql-only,oracle 
    fi
    
    echo
    echo
    echo "Building ole-app"
    cd ${OLE_DEVELOPMENT_WORKSPACE_ROOT}/ole-app
    mvn clean install -DskipTests=true

    if [ "$init_db" == "Y" ] ; then

	echo
	echo
	echo "Initializing database in ole-app/olefs"
	cd ${OLE_DEVELOPMENT_WORKSPACE_ROOT}/ole-app/olefs
	mvn initialize -Pdb -Djdbc.dba.url="jdbc:mysql://${oledbhost}" -Djdbc.url="jdbc:mysql://${oledbhost}/OLE" -Djdbc.dba.username=root -Djdbc.dba.password=${dbpassword}

	echo
	echo
	echo "Built WITHOUT demo data"
	
	echo "Creating barcode index on ole_ds_item_t"
	query="create index barcode_index on ole_ds_item_t (barcode);"
	echo $query | mysql -u OLE_DB_USER -pOLE_DB_USER_PASSWORD ole
	echo "Creating former ID index on ole_ds_holdings_t"
	query="create index former_holdings_id_index on ole_ds_holdings_t (former_holdings_id);"
	echo $query | mysql -u OLE_DB_USER -pOLE_DB_USER_PASSWORD ole

	echo "grant file on *.* to 'OLE_DB_USER'@'localhost';" | mysql -u root -p${dbpassword} 
	echo "flush privileges;" | mysql -u root -p${dbpassword}

	if [ "$buildtype" == "inst" ] ; then
	    	    	    
	# Run the docstore loader
	    
	    cd ${builddir}

	    # We'll let the docstore loader recreate the item types as it goes,
	    # rather than leaving the existing ones in there
	    query="delete from ole_cat_itm_typ_t;"
	    echo $query | mysql -u OLE_DB_USER -pOLE_DB_USER_PASSWORD ole

	    # And we'll let loadpatrons.php create the borrower types
	    query="delete from ole_dlvr_borr_typ_t;"
	    echo $query | mysql -u OLE_DB_USER -pOLE_DB_USER_PASSWORD ole

	    ${builddir}/MakeIDsAutoIncrement.pl

	    if [ "$stage_loader_data" == "Y" ] ; then
		echo "Staging loader data"
		java -Xms2g -Xmx6g -XX:MaxPermSize=4g -Dfile.encoding=UTF-8 -Dmigrationdbdir=${migrationdbdir} -Doledbhost="$oledbhost" -jar $tools_dir/StageLoaderData.jar "$sirsiexportdir"
	    fi

	    echo "Loading docstore data ..."
	    java -Xms2g -Xmx6g -XX:MaxPermSize=4g -Dfile.encoding=UTF-8 -Dmigrationdbdir=${migrationdbdir} -Doledbhost="$oledbhost" -jar $tools_dir/LU_DBLoadData.jar "$sirsiexportdir" "$datestamp" "$ludatadir" "catalog-all.KeysAndDates" "mod.catalog.marcxml" "catalog.defaultkey.mrc" "N"
	    
	    # Serials receiving records are loaded into OLE's tables, but
	    # the necessary RICE documents are not created.  Thus, we export
	    # the serials data to .csv files here, clear out the tables, and
	    # load the serials data back in with HTC's batch-process loader
	    # once the application is started up.
	    ${builddir}/export_reset_serials.sh

	# This assumes that the staging to the olemigration database has already happened
	# That should happen alongside exporting data from Sirsi
	    echo "Database initialized."
	    echo 
	    cd ${loadscriptsdir}
	    echo "Loading patrons ..."
	    php loadpatrons.php
	    php patrontooperator.php
	    
	    echo "Loading charges"
	    php loadcharges.php
	    
	# TODO: holds and bills both use an item-id, which depends on docstore data being loaded
	# Maybe we should load them at the same time as docstore data, in that loader?
	# Either that, or run it after this finishes
	    echo "Loading holds"
	    php loadholds.php
	    
	    echo "Loading bills"
	    php loadbills.php

	    # Our loaders don't use the _s or _seq sequences
	    # to generate IDs.  But, they have to be updated
	    # so the UI doesn't later generate IDs that collide
	    # with the existing ones.
	    ${builddir}/SyncSequencesWithIDs.pl
	    
	fi
	
	if [ "$buildtype" == "bootstrap" ] ; then
	    
	    cd $builddir
	    echo "Loading locations ..."
    # Load location levels, skipping the first line (column headers)
	    tail -n+2 ${builddir}/data/OLE_LOCN_LEVEL_T.csv | while read line; do
		query="INSERT INTO OLE_LOCN_LEVEL_T values($line);"
		echo $query | mysql -u OLE_DB_USER -pOLE_DB_USER_PASSWORD ole
	    done
	    
    # Load locations, skipping the first line (column headers)
	    tail -n+2 ${builddir}/data/OLE_LOCN_T.csv | while read line; do
		query="INSERT INTO OLE_LOCN_T values($line);"
		echo $query | mysql -u OLE_DB_USER -pOLE_DB_USER_PASSWORD ole
	    done
	    
	    echo "Loading circulation desk data ..."
	    tail -n+2 ${builddir}/data/OLE_CRCL_DSK_T.csv | while read line; do
		query="INSERT INTO OLE_CRCL_DSK_T values($line);"
		echo $query | mysql -u OLE_DB_USER -pOLE_DB_USER_PASSWORD ole
	    done
	    
	    tail -n+2 ${builddir}/data/OLE_CRCL_DSK_LOCN_T.csv | while read line; do
		query="INSERT INTO OLE_CRCL_DSK_LOCN_T values($line);"
		echo $query | mysql -u OLE_DB_USER -pOLE_DB_USER_PASSWORD ole
	    done
	    
	    cd ${loadscriptsdir}
	    echo "Running migration php scripts"
	    ./load.sh
	    
	fi
	
    fi

else
    echo "Building all of OLE"
    mvn clean install -DskipTests=true
    echo "Built WITH demo data"
fi

if [ "$deploy_apps" == "Y" ] ; then

# Stop tomcat, if it's running ...
    sudo /etc/init.d/${TOMCAT_VER} stop

    if [ -d $TOMCAT_DEPLOY/work/Catalina/localhost ]
    then
	sudo rm -rf $TOMCAT_DEPLOY/work/Catalina/localhost/*
    fi

    sudo rm -rf $TOMCAT_DEPLOY/webapps/*.war
    sudo rm -rf $TOMCAT_DEPLOY/webapps/oledocstore
    sudo rm -rf $TOMCAT_DEPLOY/webapps/olefs
    sudo rm ${TOMCAT_LOG}/catalina.out
    
# Now move the war files into place
    sudo cp ${OLE_DEVELOPMENT_WORKSPACE_ROOT}/ole-app/olefs/target/olefs*.war $TOMCAT_DEPLOY/webapps/
    sudo cp ${OLE_DEVELOPMENT_WORKSPACE_ROOT}/ole-docstore/ole-docstore-webapp/target/oledocstore*.war $TOMCAT_DEPLOY/webapps/
# ... and rename them
    sudo mv $TOMCAT_DEPLOY/webapps/olefs*.war $TOMCAT_DEPLOY/webapps/olefs.war
    sudo mv $TOMCAT_DEPLOY/webapps/ole-docstore*.war $TOMCAT_DEPLOY/webapps/oledocstore.war
    sudo chown ${TOMCAT_VER}:${TOMCAT_VER} $TOMCAT_DEPLOY/webapps/*.war

# Start tomcat ...
    sudo /etc/init.d/${TOMCAT_VER} start
# Wait for OLE to start, 5 minutes is probably overkill ...
    sleep 300
# Now regenerate the SOLR index
    $tools_dir/clear_solr_index.sh $docstore_url
    $tools_dir/start_docstore_index.sh $docstore_url
else
    echo "Not deploying webapps"
fi
