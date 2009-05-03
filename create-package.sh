#!/bin/bash

function die {
  echo ERROR: $*
  exit 1
}

FOLDER="musicbrowser"
VERSION=$1
ZIPFILE="musicbrowser-$VERSION.zip"

if [ $# -ne 1 ]; then
  die Missing version number
fi

if [ -f $ZIPFILE ]; then
  die $ZIPFILE already exists
fi

if [ -d $FOLDER ]; then
  die the $FOLDER folder already exists
fi

if [ ! -d "src" ]; then
  die the src folder is missing
fi

mkdir $FOLDER
cp src/* $FOLDER/
rm $FOLDER/*~
zip -r $ZIPFILE $FOLDER
