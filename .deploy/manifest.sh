#!/usr/bin/env bash

echo '{"experimental":true}' | sudo tee /etc/docker/daemon.json
mkdir $HOME/.docker
touch $HOME/.docker/config.json
echo '{"experimental":"enabled"}' | sudo tee $HOME/.docker/config.json
sudo service docker restart

echo "$DOCKER_PASSWORD" | docker login -u "$DOCKER_USERNAME" --password-stdin

TARGET=jc5x/ttrss:latest
ARM32=jc5x/ttrss:latest-arm
ARM64=jc5x/ttrss:latest-arm64
AMD64=jc5x/ttrss:latest-amd64

echo "Push latest-* builds to $TARGET"

docker manifest create $TARGET $ARM32 $ARM64 $AMD64
docker manifest annotate $TARGET $ARM32 --arch arm   --os linux
docker manifest annotate $TARGET $ARM64 --arch arm64 --os linux
docker manifest annotate $TARGET $AMD64 --arch amd64 --os linux
docker manifest push $TARGET

echo 'Done!'
# done!