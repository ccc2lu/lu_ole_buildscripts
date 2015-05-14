#!/bin/bash

# This script deletes everything in the SOLR index in preparation for full reindex

if [ $# -ne 1 ]
    then
    echo "Specify the docstore URL on the commandline, ex: $0 http://localhost:8080/oledocstore"
    exit
fi

docstore_url=$1
solr_url="$docstore_url/bib"

curl $solr_url/update --data '<delete><query>*:*</query></delete>' -H 'Content-type:text/xml; charset=utf-8'  
curl $solr_url/update --data '<commit/>' -H 'Content-type:text/xml; charset=utf-8'
