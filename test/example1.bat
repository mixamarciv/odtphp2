@echo off


::-- переходим в каталог запуска скрипта ----------------------
@SetLocal EnableDelayedExpansion
:: this_file_path - путь к текущему бат/bat/cmd файлу
@SET this_file_path=%~dp0

:: this_disk - диск на котором находится текущий бат/bat/cmd файл
@SET this_disk=!this_file_path:~0,2!

:: переходим в текущий каталог
@%this_disk%
@CD "%this_file_path%"

SET this_path=%CD%
@echo текущий каталог:
@echo %this_path%
::-------------------------------------------------------------

::-- задаем пути к пхп и самому скрипту и переходим в папку где находится скрипт ------------------------
SET php_bin_path=h:\Program\php5\

cd %php_script_path%
SET path=%path%;%WINDIR%
SET path=%path%;%php_bin_path%;%php_lib_path%
::--------------------------------------------------------------------------------------------------------

php "example1.php" > example1.html


@pause