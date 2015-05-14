#!/bin/bash

# Author: Chris Creswell (ccc2@lehigh.edu)
# Updated: 5/7/2014 
# This script deploys OLE .war web application files
# from $OLE_DEVELOPMENT_WORKSPACE_ROOT, where the application
# has theoretically been built, into an existing
# Tomcat instance, and cleans up everything left
# behind by older versions of the webapps if applicable.
# NOTE: it removes everything from 
# $TOMCAT_DEPLOY/work/Catalina/localhost
# If you're hosting other webapps in the same tomcat instance as OLE,
# this might be bad.  
# This script also removes Tomcat's old log file, since it tends to
# become too huge to open and search conveniently, and on startup 
# the log file may need to be inspected if an error occurs.
# It's easiest to just start from scratch in this case.

export TOMCAT_VER=tomcat7
export TOMCAT_HOME=/usr/share/${TOMCAT_VER}
export TOMCAT_DEPLOY=/var/lib/${TOMCAT_VER}
export TOMCAT_LOG=/var/log/${TOMCAT_VER}

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
    $Tools_dir/start_docstore_index.sh $docstore_url
