@echo off


::-- ���室�� � ��⠫�� ����᪠ �ਯ� ----------------------
@SetLocal EnableDelayedExpansion
:: this_file_path - ���� � ⥪�饬� ���/bat/cmd 䠩��
@SET this_file_path=%~dp0

:: this_disk - ��� �� ���஬ ��室���� ⥪�騩 ���/bat/cmd 䠩�
@SET this_disk=!this_file_path:~0,2!

:: ���室�� � ⥪�騩 ��⠫��
@%this_disk%
@CD "%this_file_path%"

SET this_path=%CD%
@echo ⥪�騩 ��⠫��:
@echo %this_path%
::-------------------------------------------------------------

::-- ������ ��� � �� � ᠬ��� �ਯ�� � ���室�� � ����� ��� ��室���� �ਯ� ------------------------
SET php_bin_path=h:\Program\php5\

cd %php_script_path%
SET path=%path%;%WINDIR%
SET path=%path%;%php_bin_path%;%php_lib_path%
::--------------------------------------------------------------------------------------------------------

php "example1.php" > example1.html


@pause