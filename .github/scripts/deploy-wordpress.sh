#!/usr/bin/env bash

if [[ -n "$GITHUB_WORKFLOW" ]]; then
    SCRIPT_TAG=${GITHUB_REF##*/}
else
    echo "Script is only to be run by GitHub Actions" 1>&2
    exit 1
fi

# if tag contains beta we abort
if [[ "$SCRIPT_TAG" == *"beta"* ]]; then
    echo "Tag contains beta, aborting deployment" 1>&2
    exit 0
fi

# if wordpress password isnt set we abort
if [[ -z "$WORDPRESS_PASSWORD" ]]; then
    echo "WordPress.org password not set" 1>&2
    exit 1
fi

if ! command -v svn > /dev/null 2>&1; then
    echo "svn is not installed. Attempting to install the missing dependency..."
    sudo apt-get update -y
    sudo apt-get install -y subversion
    if [ $? -ne 0 ]; then
        echo "Failed to install svn. Please install it manually."
        exit 1
    fi
    echo "svn has been successfully installed."
else
    echo "svn is installed."
fi

PROJECT_ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
PLUGIN_BUILDS_PATH="$PROJECT_ROOT/build-wp"
VERSION="$SCRIPT_TAG"

mkdir -p $PLUGIN_BUILDS_PATH/$PLUGIN

cp $DIST_DIR_GITHUB/$GITHUB_RELEASE_FILENAME $PLUGIN_BUILDS_PATH/$PLUGIN/$GITHUB_RELEASE_FILENAME

cd "$PLUGIN_BUILDS_PATH/$PLUGIN"

# Ensure the zip file for the current version has been built
if [ ! -f "$GITHUB_RELEASE_FILENAME" ]; then
    echo "Built zip file $GITHUB_RELEASE_FILENAME does not exist" 1>&2
    exit 1
fi

# Check if the tag exists for the version we are building
TAG=$(svn ls "https://plugins.svn.wordpress.org/$PLUGIN/tags/$VERSION")
error=$?
if [ $error == 0 ]; then
    # Tag exists, don't deploy
    echo "Tag already exists for version $VERSION, aborting deployment"
    exit 1
fi

# Unzip the built plugin
unzip -q -o "$GITHUB_RELEASE_FILENAME"

rm "$GITHUB_RELEASE_FILENAME"

cd ../

# Check version in readme.txt is the same as plugin file after translating both to Unix line breaks to work around grep's failure to identify Mac line breaks
PLUGINVERSION=$(grep -i "Version:" $PLUGIN/$MAINFILE | awk -F' ' '{print $NF}' | tr -d '\r')
echo "$MAINFILE version: $PLUGINVERSION"
READMEVERSION=$(grep -i "Stable tag:" $PLUGIN/readme.txt | awk -F' ' '{print $NF}' | tr -d '\r')
echo "readme.txt version: $READMEVERSION"

if [ "$READMEVERSION" = "trunk" ]; then
    echo "Version in readme.txt & $MAINFILE don't match, but Stable tag is trunk. Let's continue..."
elif [ "$PLUGINVERSION" != "$READMEVERSION" ]; then
    echo "Version in readme.txt & $MAINFILE don't match. Exiting...."
    exit 1;
elif [ "$PLUGINVERSION" = "" ] || [ "$READMEVERSION" = "" ]; then
    echo "Version in readme.txt or $MAINFILE is empty. Exiting...."
    exit 1;
elif [ "$PLUGINVERSION" != "$VERSION" ] || [ "$READMEVERSION" != "$VERSION" ]; then
    echo "Version in readme.txt or $MAINFILE does not match pushed tag. Exiting...."
    exit 1;
elif [ "$PLUGINVERSION" = "$READMEVERSION" ]; then
    echo "Versions match in readme.txt and $MAINFILE. Let's continue..."
fi

# Checkout trunk to get current state from WordPress.org
svn co "https://plugins.svn.wordpress.org/$PLUGIN/trunk" svn/trunk

# Copy our new version of the plugin into trunk (exclude .svn to preserve working copy)
rsync -r -p --delete --exclude='.svn' $PLUGIN/ svn/trunk/

# Add new files to SVN in trunk
svn stat svn/trunk | grep '^?' | awk '{print $2}' | xargs -I x svn add x@
# Remove deleted files from SVN in trunk
svn stat svn/trunk | grep '^!' | awk '{print $2}' | xargs -I x svn rm --force x@

svn stat svn/trunk

# this is so we can test a deploy without the final svn commit, if theres a hyphen in tag but doesn't contain beta in it we will get here.
if [[ "$SCRIPT_TAG" == *"-"* ]]; then
    echo "Tag contains hyphen, aborting deployment" 1>&2
    exit 0
fi

# Commit trunk to SVN
echo "Committing changes to trunk..."
svn ci --no-auth-cache --username $WORDPRESS_USERNAME --password $WORDPRESS_PASSWORD svn/trunk -m "Deploy version $VERSION"

if [ $? -ne 0 ]; then
    echo "Failed to commit to trunk" 1>&2
    exit 1
fi

echo "Trunk committed successfully!"
echo "Waiting for SVN server to synchronize..."
sleep 3

# Create new version tag from the updated trunk using svn copy
echo "Creating tag $VERSION from trunk..."
svn copy "https://plugins.svn.wordpress.org/$PLUGIN/trunk" \
         "https://plugins.svn.wordpress.org/$PLUGIN/tags/$VERSION" \
         --username $WORDPRESS_USERNAME --password $WORDPRESS_PASSWORD \
         -m "Tagging version $VERSION"

if [ $? -ne 0 ]; then
    echo "Failed to create tag $VERSION" 1>&2
    exit 1
fi

echo "Tag $VERSION created successfully!"
echo "Deployment completed successfully!"

# Remove SVN temp dir
rm -fR svn
