#!/usr/bin/env bash

echo "travis.sh: I am building on architecture ${ARCH}, branch $TRAVIS_BRANCH."

echo '{"experimental":true}' | sudo tee /etc/docker/daemon.json
mkdir $HOME/.docker
touch $HOME/.docker/config.json
echo '{"experimental":"enabled"}' | sudo tee $HOME/.docker/config.json
sudo service docker restart

# First build amd64 image:
echo "$DOCKER_PASSWORD" | docker login -u "$DOCKER_USERNAME" --password-stdin

if [ $ARCH == "arm" ]; then
    echo "Because architecture is $ARCH running some extra commands."
    docker run --rm --privileged multiarch/qemu-user-static:register --reset

    # get qemu-arm-static binary
    mkdir tmp
    pushd tmp && \
    curl -L -o qemu-arm-static.tar.gz https://github.com/multiarch/qemu-user-static/releases/download/v2.6.0/qemu-arm-static.tar.gz && \
    tar xzf qemu-arm-static.tar.gz && \
    popd
fi

LABEL=jc5x/ttrss:latest-$ARCH
echo "Going to build label $LABEL"
docker build -t $LABEL -f Dockerfile.$ARCH .
echo "Going to push label $LABEL"
docker push $LABEL

echo "Done!"