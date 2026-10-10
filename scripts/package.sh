#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
test -f vendor/dendrila-forms/src/Core.php || { echo "Moteur Dendrila Forms absent." >&2; exit 1; }
test -f vendor/dendrila-forms/forms/survey-demo.json || { echo "Exemples absents." >&2; exit 1; }
mkdir -p dist
rm -f dist/dendrila-forms-wordpress.zip
# WordPress attend un dossier racine unique dans l'archive.
mkdir -p dist/package/dendrila-forms-wordpress
cp dendrila-forms-wordpress.php readme.txt LICENSE dist/package/dendrila-forms-wordpress/
cp -R includes assets vendor dist/package/dendrila-forms-wordpress/
rm -f dist/dendrila-forms-wordpress.zip
(cd dist/package && zip -q -r ../dendrila-forms-wordpress.zip dendrila-forms-wordpress)
rm -rf dist/package
echo "dist/dendrila-forms-wordpress.zip"
