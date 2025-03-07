#!/usr/bin/env bash

pushd "$( dirname $0 )" > /dev/null
CURDIR=$( pwd -P )

## build base images
docker build -t openloyalty/base-nodejs -f nodejs-dockerfile $CURDIR --no-cache
docker build -t openloyalty/base-nginx -f nginx-dockerfile $CURDIR --no-cache
docker build -t openloyalty/base-php-fpm -f php-fpm-dockerfile $CURDIR --no-cache
docker build -t openloyalty/elasticsearch -f elasticsearch-dockerfile $CURDIR --no-cache

popd > /dev/null
