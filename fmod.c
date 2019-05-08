#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <stdbool.h>

#define _NGINX_ 1
#define _BUF_SIZE_ 1024
#define _VERSION_NGINX_ "nginx"
#define _VERSION_OPENRESTY_ "openresty"
#define _NGINX_PATH_TO_ "/usr/local/src/nginx-1.15.10"
#define _OPENRESTY_PATH_TO_ "/usr/local/src/openresty-1.13.6.2"
#define _GET_PATH_TO_ !strcmp("nginx", _VERSION_NGINX_)

void call_return(const char * call, bool output);

char buf[_BUF_SIZE_];

int main(int argc, char ** argv)
{
        if(argc == 1) {
                fputs("fmod: expect nginx version and moudle name.", stdout);
                exit(EXIT_FAILURE);
        }

        char * _version = argv[1];
        char * _module_name = argv[2];
	char * path_to;
	char call[128];
	FILE * fp;

	#if(_NGINX_)
	path_to = _NGINX_PATH_TO_;
	#else
	path_to = _OPENRESTY_PATH_TO_;
	#endif
	
	if(path_to == NULL && _GET_PATH_TO_) {
		path_to = _NGINX_PATH_TO_;
	}

	sprintf(call, "%s %s %s %s", "find ", path_to, " -name 'ngx_modules.c' | xargs cat | grep ", _module_name);
	call_return(call, false);
	if(!*buf) {
		printf("%s %s %s", "fmod:", _module_name, "not loaded", stdout);
		exit(EXIT_FAILURE);
	} else {
		printf("%s %s %s", "fmod:", _module_name, "has loaded", stdout);
	}

	return 1;
}

void call_return(const char * call, bool output) 
{
	extern char buf[];
	FILE * fp = popen(call, "r");
	fgets(buf, _BUF_SIZE_, fp);
	if(output)
		puts(buf);
}

