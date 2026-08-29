@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"

rem ============================================================================
rem  Summarize with AI - release builder
rem
rem  Double-click this file. It produces dist\summarize-with-ai-wp-<version>.zip,
rem  ready to upload through Plugins > Add New > Upload Plugin.
rem
rem  The folder inside the ZIP is summarize-with-ai-wp, matching the GitHub repo
rem  slug and the folder already installed on production, so WordPress treats the
rem  upload as an update rather than a second copy of the plugin.
rem
rem  Uses only tools built into Windows: robocopy for the file copy and
rem  PowerShell for the ZIP. Nothing to install.
rem ============================================================================

set "SLUG=summarize-with-ai-wp"
set "MAIN=summarize-with-ai.php"
set "STAGE=.build"
set "DIST=dist"

echo.
echo  Summarize with AI - release builder
echo  ===================================
echo.

if not exist "%MAIN%" (
    echo  ERROR: %MAIN% not found.
    echo  Run this from the plugin folder.
    goto :fail
)

rem --------------------------------------------------------------- version ---
rem Read the version three times over, from the places that must agree.

set "VERSION="
for /f "tokens=2 delims=:" %%A in ('findstr /B /C:" * Version:" "%MAIN%"') do set "VERSION=%%A"
for /f "tokens=*" %%A in ("!VERSION!") do set "VERSION=%%A"

set "CONSTVER="
for /f "tokens=4 delims='" %%A in ('findstr /B /C:"define( 'SWI_VERSION'" "%MAIN%"') do set "CONSTVER=%%A"

set "STABLE="
if exist "readme.txt" (
    for /f "tokens=2 delims=:" %%A in ('findstr /B /C:"Stable tag:" "readme.txt"') do set "STABLE=%%A"
    for /f "tokens=*" %%A in ("!STABLE!") do set "STABLE=%%A"
)

if "!VERSION!"=="" (
    echo  ERROR: could not read the Version header from %MAIN%.
    goto :fail
)

echo  Version header    : !VERSION!
echo  SWI_VERSION       : !CONSTVER!
echo  readme Stable tag : !STABLE!
echo.

rem A version mismatch ships an update that never triggers, or a changelog that
rem describes the wrong release. Stop rather than build something misleading.
set "MISMATCH="
if not "!CONSTVER!"=="!VERSION!" set "MISMATCH=1"
if not "!STABLE!"=="" if not "!STABLE!"=="!VERSION!" set "MISMATCH=1"

if defined MISMATCH (
    echo  ERROR: these three must match before a release is built.
    echo         Update the Version header, the SWI_VERSION constant and
    echo         the Stable tag in readme.txt, then run this again.
    goto :fail
)

rem ------------------------------------------------------------------ lint ---
rem Best effort. Laragon keeps PHP off the PATH, so look there too.

set "PHPBIN="
for /f "delims=" %%A in ('where php 2^>nul') do if not defined PHPBIN set "PHPBIN=%%A"
if not defined PHPBIN for /d %%D in ("C:\laragon\bin\php\php-*") do if exist "%%D\php.exe" set "PHPBIN=%%D\php.exe"
if not defined PHPBIN for /d %%D in ("D:\laragon\bin\php\php-*") do if exist "%%D\php.exe" set "PHPBIN=%%D\php.exe"

set "LINTFAIL="

if defined PHPBIN (
    echo  Checking PHP syntax...
    for /r %%F in (*.php) do (
        echo %%F | findstr /I /C:"\.build\\" /C:"\_dev\\" /C:"\dist\\" >nul || (
            "!PHPBIN!" -l "%%F" >nul 2>&1 || (
                echo    SYNTAX ERROR: %%F
                set "LINTFAIL=1"
            )
        )
    )
) else (
    echo  PHP not found, skipping the syntax check.
)

rem GOTO is kept out of nested blocks: cmd.exe resolves labels far more
rem reliably from the top level.
if defined LINTFAIL (
    echo.
    echo  ERROR: PHP syntax errors above. Nothing was built.
    goto :fail
)

if defined PHPBIN echo    all files OK
echo.

rem ----------------------------------------------------------------- stage ---

if exist "%STAGE%" rd /s /q "%STAGE%"
mkdir "%STAGE%\%SLUG%" >nul 2>&1

echo  Staging files...

robocopy "." "%STAGE%\%SLUG%" /E /NFL /NDL /NJH /NJS /NP ^
    /XD ".git" ".github" "_dev" "dist" "%STAGE%" "node_modules" "vendor" ".vscode" ".idea" ^
    /XF "build.bat" ".gitignore" ".gitattributes" "*.zip" "*.log" "*.bak" "*.orig" "*.rej" ^
        "*-temp.html" "Thumbs.db" ".DS_Store" "desktop.ini" >nul

if %ERRORLEVEL% GEQ 8 (
    echo  ERROR: robocopy failed with code %ERRORLEVEL%.
    goto :fail
)

if not exist "%STAGE%\%SLUG%\%MAIN%" (
    echo  ERROR: %MAIN% did not make it into the staging folder.
    goto :fail
)

set "FILECOUNT=0"
for /r "%STAGE%\%SLUG%" %%F in (*) do set /a FILECOUNT+=1
echo    !FILECOUNT! files

rem ------------------------------------------------------------------- zip ---

if not exist "%DIST%" mkdir "%DIST%"

set "ZIPNAME=%SLUG%-!VERSION!.zip"
set "ZIPPATH=%CD%\%DIST%\!ZIPNAME!"

if exist "!ZIPPATH!" (
    echo    replacing existing !ZIPNAME!
    del /q "!ZIPPATH!"
)

echo  Creating !ZIPNAME! ...

rem Entry names are built by hand rather than with CreateFromDirectory.
rem
rem The ZIP format requires forward slashes, but CreateFromDirectory on .NET
rem Framework, which is what Windows PowerShell 5.1 runs on, writes the platform
rem separator instead. The resulting archive looks fine on Windows and then
rem extracts on a Linux host as one flat pile of files literally named
rem "summarize-with-ai-wpdmin\settings.php", which breaks the plugin.
rem
rem So: open the archive, add each file under a name we control, then reopen it
rem and refuse to ship if a single entry still carries a backslash.
powershell -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; Add-Type -AssemblyName System.IO.Compression.FileSystem; $sep=[string][char]92; $src=(Resolve-Path ('%CD%' + $sep + '%STAGE%')).Path; $dst='!ZIPPATH!'; $zip=[System.IO.Compression.ZipFile]::Open($dst,'Create'); Get-ChildItem -LiteralPath $src -Recurse -File ^| Sort-Object FullName ^| ForEach-Object { $rel=$_.FullName.Substring($src.Length+1).Replace($sep,'/'); [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip,$_.FullName,$rel,'Optimal') }; $zip.Dispose(); $chk=[System.IO.Compression.ZipFile]::OpenRead($dst); $all=@($chk.Entries); $bad=@($all ^| Where-Object { $_.FullName.Contains($sep) }); $n=$all.Count; $b=$bad.Count; $chk.Dispose(); if ($b -gt 0) { Write-Host ('   ERROR: ' + $b + ' of ' + $n + ' entries use backslashes'); exit 1 }; Write-Host ('   ' + $n + ' entries, all forward slashes')"

if errorlevel 1 (
    echo  ERROR: the archive was written with invalid path separators.
    goto :fail
)

if not exist "!ZIPPATH!" (
    echo  ERROR: the ZIP was not created.
    goto :fail
)

rd /s /q "%STAGE%"

for %%F in ("!ZIPPATH!") do set "ZIPSIZE=%%~zF"
set /a ZIPKB=!ZIPSIZE! / 1024

echo.
echo  ===================================
echo   Built: %DIST%\!ZIPNAME!
echo   Size : !ZIPKB! KB
echo   Root : %SLUG%\
echo  ===================================
echo.
echo  Upload it at Plugins ^> Add New ^> Upload Plugin.
echo  WordPress will offer to replace the installed copy.
echo.

pause
endlocal
exit /b 0

:fail
echo.
if exist "%STAGE%" rd /s /q "%STAGE%"
echo  Build aborted.
echo.
pause
endlocal
exit /b 1
