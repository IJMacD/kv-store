#!/bin/bash

set -o errexit
set -o nounset
set -o pipefail

if [ -n "$(git status --porcelain)" ]; then
  echo "Please ensure there are no changes or untracked files before rebuilding"
  exit 1
fi

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
source ${SCRIPT_DIR}/vars.sh

CURRENT_CONTEXT=$(kubectl config current-context)

case "$CURRENT_CONTEXT" in
  contabo|contabo-tunnel) ;;
  *)
    echo "Refusing to deploy: kubectl context is '$CURRENT_CONTEXT' (expected contabo or contabo-tunnel)"
    exit 1
    ;;
esac

echo "Building and deploying version $GIT_TAG to cluster $CURRENT_CONTEXT"

export MARIADB_ROOT_PASSWORD=$(kubectl get secret --namespace "$APPNAME" $APPNAME-mariadb -o jsonpath="{.data.mariadb-root-password}" 2>/dev/null | base64 -d)
export MARIADB_PASSWORD=$(kubectl get secret --namespace "$APPNAME" $APPNAME-mariadb -o jsonpath="{.data.mariadb-password}" 2>/dev/null | base64 -d)

if [ -z "$MARIADB_ROOT_PASSWORD" ]; then
    export MARIADB_ROOT_PASSWORD=$(openssl rand -base64 18)
fi
if [ -z "$MARIADB_PASSWORD" ]; then
    export MARIADB_PASSWORD=$(openssl rand -base64 18)
fi

for project in $PROJECTS; do
  docker buildx build \
    --builder kube-builder \
    --platform linux/amd64 \
    --push \
    -f ${SCRIPT_DIR}/Dockerfile \
    -t ${REGISTRY_NAME}/${REPO}/${project}:${GIT_TAG} \
    ${SCRIPT_DIR}
done

helm upgrade --install ${APPNAME} \
  $SCRIPT_DIR/kube/chart/${APPNAME}/ \
  --namespace ${APPNAME} --create-namespace \
  --set appVersion=${GIT_TAG} \
  --set mariadb.auth.rootPassword=$MARIADB_ROOT_PASSWORD \
  --set mariadb.auth.password=$MARIADB_PASSWORD \
  $@
