# A simple plugin for SimpleSAMLPHP with Argo2Id

## Purpose.
This plugin enable SimpleSAMLPHP to authenticate user with Argo2Id password stored in SQL databases (prefer MySQL / PostgreSQL)

## Steps to setup
At the moment, pull the repository to your local place, extract the whole folder then
1. Put under `modules` folder of SimpleSAMLPHP.
2. Prepare a authsource in `config/authsources.php` file with and entry under config array. (You may want to refer to #docs folder for sample)
3. Prepare proper SQL schemas with samples per #docs.