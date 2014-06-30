odtphp2
=======

библиотека для генерации openOffice документов по шаблонам

идея и методы класса взяты из библиотеки odtphp https://github.com/cybermonde/odtphp

основные отличия в том что odtphp2 может генерить документы любой сложности и размеров
но ресурсов при этом потре*лять будет меньше
 +код я постарался по возможности упростить и сократить
 +все методы из odtphp будут работать и тут.
 +добавлена функция save() - для сохранения на диск(а не в опер.памяти) сгенерированных блоков документа


PS: большие odt документы (более 10к стр.) ooffice очень долго открывает и не всегда успешно, советую сразу конвертить их в pdf
[code]
"d:\port_programs\office\LibreOfficePortable\App\libreoffice\program\soffice.exe" --headless --invisible --nocrashreport --convert-to pdf --nodefault --nofirststartwizard --nologo --norestore "c:\ОБЯЗАТЕЛЬНО\полный\путь\к\документу.odt"
[/code]


УДАЧИ!