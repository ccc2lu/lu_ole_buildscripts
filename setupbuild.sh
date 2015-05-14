#!/bin/bash

# Author: Chris Creswell (ccc2@lehigh.edu)
# Updated: 5/7/2014
# This script prompts for and sets a bunch of environment variables
# that are used by UpdatedAndBuildOLE.sh.  It must be sourced when
# you run it for the variables it sets to remain set in your shell, 
# so you run it by saying
# . setupbuild.sh
# This thing abuses eval something fierce so that the variable names,
# prompts, and default values can all go in the arrays at the top.

variables=(builddir buildtype demodata server datestamp ludatadir migrationdbdir sirsiexportdir remotedest new_export stage_export stage_loader_data init_db oledbhost docstore_url deploy_apps)
prompts=("Build directory?" "Build type?" "Build with demo data?" "Server to get export from?" "Date of Sirsi export?" "Directory where SFX export and locations .csv file is found?" "Directory where SQLite migration database file goes?" "Datestamped local directory where Sirsi export should go?" "Remote directory where Sirsi export should go?" "Get a new export from Sirsi?" "Stage patrons, holds, charges, etc in migration database?" "Stage items, callnumbers in migration database?" "Initialize database?" "OLE database host?" "URL of docstore webapp?" "Deploy webapps after build, and restart tomcat?")
defaults=(`pwd` "inst" "N" "sirsi.server.hostname" `date +%Y%m%d` "/usr/local/oledev/dump_convert_load/LehighData" "$loadscriptsdir" "/usr/local/oledev/sirsidump/"`date +%Y%m%d` "/sirsi/s/sirsi/Unicorn/Xfer/$datestamp" "N" "Y" "Y" "Y" "localhost" "http://${oledbhost}/oledocstore" "Y")

for ((i=0; i<${#variables[@]}; i++)); 
do
    # If the variable already has a value, then that 
    # existing value is the default.  Otherwise, if the
    # variable is not set yet, then use the value in the 
    # defaults array.
    eval default='$'${variables[$i]}
    if [ -z "$default" ] ; then
	default=${defaults[$i]}
    fi
    echo ${prompts[$i]}"[$default]"
    read input
    # If they just press enter, use the default value.
    # Otherwise, use the value the user gave
    if [ -z "$input" ] ; then
	eval export ${variables[$i]}=$default
    else
	eval export ${variables[$i]}="$input"
    fi
done

export tools_dir="$builddir"
