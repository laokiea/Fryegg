#! /bin/bash

_version=$1
_module_name=$2

_openresty_path_to="/usr/local/src/openresty-1.13.6.2/"
_nginx_path_to="/usr/local/src/nginx-1.15.10/"

if [ $_version == "nginx" ]; then
	cd $_nginx_path_to
#	echo `pwd`
else
	cd $__openresty_path_to
#	echo `pwd`
fi

_fr=`find ./ -name "ngx_modules.c"`
if [ -z $_fr ]; then
	echo "fmod: find nothing about ngx_modules.c"
	exit 0
else
	_gr=`grep $2 $_fr`
	if [ $? -eq 0 ]; then
		echo "fmod: $2 has loaded."
		exit 1
	else 
		echo "✘ fmod: not find any $2 module."
		exit 0
	fi
fi

