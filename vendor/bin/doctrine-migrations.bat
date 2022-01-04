@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/doctrine-migrations
php "%BIN_TARGET%" %*
