#!/bin/bash

PROGRESS_FILE=/tmp/kTwinkly_dep
if [ ! -z $1 ]; then
	PROGRESS_FILE=$1
fi

echo 0 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*               Install dependencies                   *"
echo "********************************************************"
echo "> Progress file: " ${PROGRESS_FILE}
echo "*"
echo "* Update package list"
echo "*"
apt-get update
echo "*"
echo 10 > ${PROGRESS_FILE}
apt-get -y install mitmproxy
echo 100 > ${PROGRESS_FILE}
rm ${PROGRESS_FILE}

echo "********************************************************"
echo "*             End dependencies Installation            *"
echo "********************************************************"
