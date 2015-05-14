#!/bin/bash

# Author: Chris Creswell (ccc2@lehigh.edu)
# Updated: 5/7/2014
# This script starts a full SOLR index in OLE

if [ $# -ne 1 ]
    then
    echo "Specify the docstore URL on the commandline, ex: $0 http://localhost:8080/oledocstore"
    exit
fi

docstore_url="$1"
solr_url="$docstore_url/bib"
admin_url="$docstore_url/admin.jsp"

#echo "docstore_url: $1, solr_url: $solr_url, admin_url: $admin_url"

echo "Starting index of bibliographic records, visit $docstore_url/rebuildIndex?action=status to check progress ..."
#curl --data "docCategory=work&documentType=bibliographic&docFormat=all&action=start" $docstore_url/rebuildIndex
curl --data "action=start" $docstore_url/rebuildIndex

# This second step of the indexing process has been eliminated
#echo 
#echo "Waiting for 60 seconds before continuing to index ..."
#sleep 60
#echo

#index_instance_url="$solr_url/dataimport?command=full-import&clean=false&commit=true"
#index_instance_status_url="$solr_url/dataimport?command=status"
#echo "Starting index of items and holdings, visit $index_instance_status_url to check status ..."
#curl $index_instance_url
