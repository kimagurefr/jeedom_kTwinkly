#!/bin/bash

PROGRESS_FILE=/tmp/jeedom/kTwinkly/dependencies
if [ ! -z $1 ]; then
	PROGRESS_FILE=$1
fi

echo 0 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*         Install kTwinkly plugin dependencies         *"
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
echo "* Looking for Python 3.7"
if [ $(python3.7 --version 2>&1 | grep -c 'Python 3.7.') == "1" ]; then
    echo "** Python 3.7 is already installed"
else
    echo "** Python 3.7 not installed"
    echo "* Looking for Python 3.7 package in Debian repositories"
    sudo apt-cache show python3.7
    if [ $? -eq 0 ] ; then
        echo "** Python 3.7 found. Installing with apt-get"
        sudo apt-get install -y python3.7
    else
        echo "** Python3.7 not found in debian repos."
        echo "* Installation Python 3.7 from sources"
        echo "** Install required modules to build Python 3.7.3"
        sudo apt-get install -y build-essential zlib1g-dev libncurses5-dev libgdbm-dev libnss3-dev libssl-dev libreadline-dev libffi-dev libsqlite3-dev
        echo 20 > ${PROGRESS_FILE}
    
        echo "** Download Python 3.7.3 from https://www.python.org"
        curl https://www.python.org/ftp/python/3.7.3/Python-3.7.3.tar.xz -o /tmp/Python-3.7.3.tar.xz
        echo 30 > ${PROGRESS_FILE}
    
        echo "** Extract Python 3.7.3 sources"
        tar xf /tmp/Python-3.7.3.tar.xz -C /tmp/
        echo 40 > ${PROGRESS_FILE}
    
        echo "** Prepare to build Python"
        pushd /tmp/Python-3.7.3/
        ./configure
        echo 50 > ${PROGRESS_FILE}

        echo "** Build Python"
        make
        echo 70 >> ${PROGRESS_FILE}

        echo "** Install Python in altinstall mode"
        sudo make altinstall

        . /etc/os-release 2>/dev/null
        if [ "${ID}" == "raspbian" ] && [ "${VERSION_ID}" == "9" ] && [ -f /etc/pip.conf ]; then
            if [ $(grep -c '^extra-index-url=https:\/\/www.piwheels.org\/simple' /etc/pip.conf) -eq 1 ]; then
                echo "** Plugin is running on Raspberry Pi with Debian 9/Stretch - temporarily disabling PyWheels to circumvent issue with OpenSSL 1.1.0"
                sudo sed -i 's/^extra-index-url=https:\/\/www.piwheels.org\/simple/#extra-index-url=https:\/\/www.piwheels.org\/simple/g' /etc/pip.conf
                PIPCONF_UPDATED=1
            fi 
        fi

        popd
    fi
fi

python3.7 --version
echo "*"
echo 80 > ${PROGRESS_FILE}

echo "* Install mitmproxy module and dependencies on Python 3.7"
python3.7 -m pip install tornado mitmproxy 
echo 90 > ${PROGRESS_FILE}

if [ "$PIPCONF_UPDATED" == "1" ]; then
    echo "* Enabling PyWheels again"
    sudo sed -i 's/^#extra-index-url=https:\/\/www.piwheels.org\/simple/extra-index-url=https:\/\/www.piwheels.org\/simple/g' /etc/pip.conf
fi

echo 100 > ${PROGRESS_FILE}
echo $(date)

echo "********************************************************"
echo "*             End dependencies Installation            *"
echo "********************************************************"

rm ${PROGRESS_FILE}
