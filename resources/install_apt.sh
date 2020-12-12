#!/bin/bash

PROGRESS_FILE=/tmp/jeedom/kTwinkly/dependencies
if [ ! -z $1 ]; then
	PROGRESS_FILE=$1
fi

echo 0 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*               Install dependencies                   *"
echo "********************************************************"
echo "> Progress file: " ${PROGRESS_FILE}
echo $(date)
echo "*"
echo "* Update package list"
echo "*"
sudo apt-get update
echo 5 > ${PROGRESS_FILE}
echo "*"
echo "* Remove apt package for mitmproxy"
sudo apt-get remove -y mitmproxy
echo 10 > ${PROGRESS_FILE}
echo "*"
echo "* Python 3.7"
if python3.7 --version 2>&1 | grep 'Python 3.7.'; then
    echo "* Already installed"
else
    echo "* Looking for package in Debian repositories"
    sudo apt-cache show python3.7
    if [ $? -eq 0 ] ; then
        sudo apt-get install -y python3.7
    else
        echo "* Python3.7 not found in debian repos. Installation manually."
        echo "* Install required modules to build Python 3.7.3"
        sudo apt-get install -y build-essential zlib1g-dev libncurses5-dev libgdbm-dev libnss3-dev libssl-dev libreadline-dev libffi-dev libsqlite3-dev
        echo 20 > ${PROGRESS_FILE}
    
        echo "* Download Python 3.7.3 from https://www.python.org"
        curl https://www.python.org/ftp/python/3.7.3/Python-3.7.3.tar.xz -o /tmp/Python-3.7.3.tar.xz
        echo 30 > ${PROGRESS_FILE}
    
        echo "* Unpack Python 3.7.3 sources"
        tar xf /tmp/Python-3.7.3.tar.xz -C /tmp/
        echo 40 > ${PROGRESS_FILE}
    
        echo "* Prepare to build Python"
        pushd /tmp/Python-3.7.3/
        ./configure
        echo 50 > ${PROGRESS_FILE}

        echo "* Build Python"
        make
        echo 70 >> ${PROGRESS_FILE}

        echo "* Install Python"
        sudo make altinstall
    
        popd
    fi
fi

python3.7 --version
echo "*"
echo 80 > ${PROGRESS_FILE}

echo "* Install mitmproxy"
python3.7 -m pip install tornado mitmproxy 
echo 90 > ${PROGRESS_FILE}


echo 100 > ${PROGRESS_FILE}
echo $(date)

echo "********************************************************"
echo "*             End dependencies Installation            *"
echo "********************************************************"

rm ${PROGRESS_FILE}
