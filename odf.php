<?php
/**
 * Templating class for odt file
 * You need PHP 5.2 at least
 * You need 7z in PATH 
 *
 * odtphp2 - библиотека для генерации openOffice документов по шаблонам
 * 
 * идея и методы класса взяты из библиотеки odtphp https://github.com/cybermonde/odtphp
 *   
 * 
 * основные отличия в том что odtphp2 может генерить документы любой сложности и размеров
 * но ресурсов при этом потре*лять будет меньше
 *  +код я постарался по возможности упростить и сократить
 *  +все методы из odtphp будут работать и тут.
 *  +добавлена функция save() - для сорнения на диск(а не в опер.памяти) сгенерированных блоков документа
 * 
 * 
 * 
 * УДАЧИ!
 *
 * @author     mixamarciv   mailto:mixamarciv@gmail.com
 * @copyright  GPL License 2008 
 * @license    http://www.gnu.org/copyleft/gpl.html  GPL License
 */

class OdfException extends Exception
{}


 $ODF2_DELIMITER_LEFT  = '{';
 $ODF2_DELIMITER_RIGHT = '}';
 $ODF2_PATH_TO_TMP     = null;

 $ODF2_PIXEL_TO_CM = 0.026458333;
    
class Odf_config {
    public $filename;
    public $temppath;
}

class Odf_meta_file {
    
    protected $add_files_count;
    protected $path_to_file;
    protected $pos_to_end_data_file;

    protected $path_to_temp_file;
    protected $desript_temp_file;
    protected $base_odf_path;
    
    
    public function __construct($path_to_extracted_odf_file){
	$this->add_files_count = 0;
	$this->base_odf_path = $path_to_extracted_odf_file."/";
	$this->path_to_file = $path_to_extracted_odf_file."/META-INF/manifest.xml";
    }
    
    protected function _first_add_file(){
	$content_meta = file_get_contents($this->path_to_file);
	if ($content_meta === false){
	    throw new OdfException("Nothing to parse - check that the '{$this->path_to_file}' file is correctly formed");
	}
	
	$pos = strpos($content_meta,"<manifest:file-entry");
	$pos = strpos($content_meta,"<manifest:file-entry",$pos+5);
	$this->pos_to_end_data_file = $pos;
	
	$content_meta = substr($content_meta,0,$pos);
	
	$this->path_to_temp_file = $this->path_to_file.".new";
	$this->desript_temp_file = fopen($this->path_to_temp_file,'w');
	fwrite($this->desript_temp_file,$content_meta);
	
	
	$path_to_pictures = $this->base_odf_path."Pictures";
	if(!file_exists($path_to_pictures)) mkdir($path_to_pictures);
    }
    
    public function add_image_file($file){
	if(++$this->add_files_count==1) $this->_first_add_file();
	$new_file = "Pictures/".basename($file);
	$new_file_path = $this->base_odf_path.$new_file;
	try{
	    copy($file,$new_file_path);
	}catch(Error $e){
	    throw new OdfException("cant copy file '{$file}' to '{$new_file_path}' ;");
	}
	
	$add_xml = "<manifest:file-entry manifest:full-path=\"{$new_file}\" manifest:media-type=\"\"/>\n ";
	
	fwrite($this->desript_temp_file,$add_xml);
    }
    
    public function save(){
	if($this->add_files_count==0) return;
	$f = fopen($this->path_to_file,'r');
	fseek($f,$this->pos_to_end_data_file);
	$end_data = fread($f,filesize($this->path_to_file));
	fclose($f);
	
	fwrite($this->desript_temp_file,$end_data);
	fclose($this->desript_temp_file);
	
	unlink($this->path_to_file);
	rename($this->path_to_temp_file,$this->path_to_file);
    }
    
}

class Odf implements /*IteratorAggregate,*/ Countable {
    
    protected $level;
    protected $parent_odf; 
    protected $config;
    protected $odf_meta_file;
    protected $xml;
    protected $parsedxml;    

    protected $segment_name;
    protected $hide;     //no merge all data segment

    protected $vars = array();
    protected $segments = array();        //name->Odf
    protected $segments_order = array();  //n_order->array('name'=>имя блока,'pos'=>позиция в тек.блоке,'use_temp'=>bool) сегменты в порядке следования

    protected $use_temp;
    protected $parsedxml_temp_file_path;
    protected $parsedxml_temp_file_d;

    
    public function get_sub_segments_names(){
	return array_keys($this->segments);
    }
    
    public function getName(){
	return $this->segment_name;
    }
    public function &get_config(){
	if($this->level==0) return $this->config;
	return $this->parent_odf->get_config();
    }
    public function &get_odf_meta(){
	if($this->level==0) return $this->odf_meta_file;
	return $this->parent_odf->get_odf_meta();
    }
    public function hasChildren(){
	return count($this->segments);
    }
    public function hasChildren_with_temp(){
	foreach ($this->segments as $seg){
		if($seg->use_temp) return 1;
	}
	return 0;
    }
    public function count(){
	return count($this->segments);
    }
    public function __get($prop){
	return $this->setSegment($prop);
    }
    public function __call($meth, $args){
        try {
            array_unshift($args,$meth);
	    //echo "<pre>dump __call {$this->segment_name} :";
	    //echo var_dump($args);
	    //echo "</pre>";
            return call_user_func_array(array($this,'setVars'),$args);
        } catch (SegmentException $e) {
            throw new OdfException("method $meth nor var $meth exist");
        }
    }
    public function is_hidden(){ return $this->hide; }
    public function hide($need_hide){ $this->hide = $need_hide; }

    public function __construct($filename_or_block_name, $xml_data=null, $parent=null, $level=null){
	
	$this->hide = 0;
	$this->use_temp = 0;
	$this->parsedxml_temp_file_d = 0; //дескриптор временного файла
	if($level==null){
            $this->segment_name =  'MAIN';
	    $this->level = 0;
	    $this->parent_odf = null;
	    
	    $this->config = new Odf_config();

	    
	    $this->config->filename = $filename_or_block_name;
	    
	    
	    $this->_extract_and_read_odf_files();
	    $this->odf_meta_file = new Odf_meta_file($this->config->temppath);
	    
	    $this->parsedxml_temp_file_path = $this->get_config()->temppath."/content.xml"; //если это главный блок, то для него временных файлов не создаем!
	    
	    
	    $this->_clear_user_vars();
	    $this->xml = $this->_prepare_row_blocks($this->xml);
	    
	    
	}else{
	
	    $this->segment_name = $filename_or_block_name;
	    $this->xml          = $xml_data;
	    $this->parent_odf   = $parent;
	    $this->level        = $level;
	    
	    $this->parsedxml_temp_file_path = $this->get_config()->temppath."/temp_segment_{$this->segment_name}.xml";
	}
        
	$this->_analyse_children_segments($this->xml);
    }
    
    protected function _analyse_children_segments($xml){
	//$reg2 = "#<table\:table-row[^>]*>.{1,200}\[!--\sBEGIN\s([\S]*)\s--\](.*)\[!--\sEND\s(\\1)\s--\].*<\/table\:table-row>#Usm";
	$reg2 = "#\[!-- BEGIN ([\S]*) --\](.*)\[!-- END (\\1) --\]#Usm";
        preg_match_all($reg2, $xml, $matches);
	
	//my_var_dump_html2("\$matches",$matches);
	
        for ($i = 0, $size = count($matches[0]); $i < $size; $i++){
	    $name = $matches[1][$i];

            if ($name != $this->segment_name){
		my_var_dump_html2("segment[".$this->segment_name."]",$name);
                $this->segments[$name] = new Odf($name, $xml=$matches[0][$i], $this, $this->level+1);
            } else {
                $this->_analyse_children_segments($matches[2][$i]);
            }
        }
        return $this;
    }
    
    //------------------------------------
    
    
    private function _extract_and_read_odf_files($need_copy=0,$old_cmd=""){
	$file_name = $this->config->filename;
	$base_name = basename($file_name);
	
	$temp_path = sys_get_temp_dir()."/odtphp2";
	if(!file_exists($temp_path)){
	    mkdir($temp_path);
	}
	$temp_path .= "/".date("Ymd-H");
	if(!file_exists($temp_path)){
	    mkdir($temp_path);
	}
	$temp_path .= "/".date("Ymd_His_").substr(uniqid(),10,3)."_{$base_name}";
	
	if($need_copy==1){
	    $file_name2 = $temp_path.".7z";
	    copy($file_name,$file_name2);
	    $file_name = $file_name2;
	}
	
	$cmd = "7z x \"{$file_name}\" -o\"{$temp_path}\"";
	//echo "<h3>run_cmd:".$cmd."</h3>";
	exec( $cmd );
        
	$this->config->temppath = $temp_path;
	
	$xml_file = "{$temp_path}/content.xml";
	
	if(!file_exists($xml_file)){
	    if($need_copy==1){
		throw new OdfException("cant extract archive: {$this->config->filename}");
	    }
	    return $this->_extract_and_read_odf_files(1,$cmd);
	}
	
	$file_data = file_get_contents($xml_file);
	if($file_data==""){
	    echo "<h3>cmd:".$cmd."</h3>";
	    throw new OdfException("cant read file: {$xml_file}");
	}
	
	$this->xml = $file_data;
    }
    public function __destruct(){
	if($this->level==0){
	    //echo "<h3>temp_path:".$this->config->temppath."</h3>";
	    //rrmdir($this->config->temppath);
	}
    }
    
    //меняем row.blocks на просто blocks
    //и перемещаем начало блока перед ближайшей '<table:table-row' и окончание блока после ближайшего '</table:table-row>'
    private function _prepare_row_blocks(&$p_sub_contentXml,$level=0){
	// Search all possible rows in the document
	//этот вариант позоволяет использовать строки внутри строк 
	$begin_text = '<table:table-row';
	$end_text = '</table:table-row>';
	
	$i_rep = 0;
	$matches = 1;
	$next_offset = 0;
	while($matches && $i_rep++<100){
	    preg_match('#(\[!-- BEGIN (row\.[\S]*) --\])(.*)(\[!-- END \\2 --\])#sm',$p_sub_contentXml,$matches,PREG_OFFSET_CAPTURE,$next_offset);
	    
	    if(!$matches) break;
	    
	    my_var_dump_html2("\$matches",$matches);

	    $begin_segment     = $matches[1][0];
	    $begin_segment_pos = $matches[1][1];
	    $p_sub_contentXml  = str_replace($begin_segment,"",$p_sub_contentXml);
	    
	    
	    $end_segment      = $matches[4][0];
	    $end_segment_pos  = $matches[4][1] - strlen($begin_segment);
	    $p_sub_contentXml = str_replace($end_segment,"",$p_sub_contentXml);
	    
	    
	    $new_begin_segment    = str_ireplace("row.","",$begin_segment);
	    $new_end_segment      = str_ireplace("row.","",$end_segment);
	    
	    $new_pos_end = strpos($p_sub_contentXml,$end_text,$end_segment_pos);
	    if($new_pos_end===false){
		throw new OdfException("end row not found! for segment \"$end_segment\"(segment '".$this->getName()."')");
	    }
	    $new_pos_end += strlen($end_text);
	    $p_sub_contentXml = substr_replace($p_sub_contentXml,$new_end_segment,$new_pos_end,0);
	    
	    
	    $subxml = substr($p_sub_contentXml,0,$begin_segment_pos);
	    $new_pos_begin = strrpos($subxml,$begin_text,0);
	    if($new_pos_begin===false){
		my_var_dump_html2("\$subxml len",strlen($subxml));
		my_var_dump_html2("\$subxml",$subxml);
		throw new OdfException("begin row not found! for segment \"$begin_segment\" (segment '".$this->getName()."')");
	    }
	    
	    $p_sub_contentXml = substr_replace($p_sub_contentXml,$new_begin_segment,$new_pos_begin,0);
	    
	    my_var_dump_html2("new_segments",array($begin_segment,$new_begin_segment,$end_segment,$new_end_segment));
	    
	    $next_offset = $new_pos_begin + 1; //hehe
	}
	return $p_sub_contentXml;
    }
    
    // очистка переменных пользователя от мусора
    // clear user template vars from tags
    //  f.e. "[!-- BEGIN row.row</text:span><text:span text:style-name="T15">2</text:span><text:span text:style-name="T11"> --]"
    //  to [!-- BEGIN row.row2 --]
    private function _clear_user_vars(){
        $xml = &$this->xml;
	$reg = "#\[!--.{1,500}(BEGIN|END).{1,500}--\]#Usm";
	
	preg_match_all($reg, $xml, $matches);
	if(count($matches)>0)
	    for($i=0;$i<count($matches[0]);$i++){
		if(strpos($matches[0][$i],"<")!==false){
		    $new_var = preg_replace("#<\/?text:span[^>]*>#iUsm","",$matches[0][$i]);
		    $new_var = preg_replace("#<[^>]*>#iUsm","",$new_var);
		    $xml = str_replace($matches[0][$i],$new_var,$xml);
		    //my_var_dump_html2("\$matches[0][$i]",$matches[0][$i]);
		    //my_var_dump_html2("\$new_var",$new_var);
		}
	    }
	//my_var_dump_html2("\$matches_all",$matches);
    }
    
    //-------------------------------------------
    
    private function _add_image_file($file){
	$this->get_odf_meta()->add_image_file($file);
    }
    
    public function setVars($key, $value, $encode = true, $charset = 'ISO-8859'){
        global $ODF2_DELIMITER_LEFT , $ODF2_DELIMITER_RIGHT;
	$var_name = $ODF2_DELIMITER_LEFT. $key . $ODF2_DELIMITER_RIGHT;

        if (strpos($this->xml, $var_name) === false){
	    //my_var_dump_html2("\$this->segments",$this->segments);
            throw new OdfException("var $key not found in the document (segment '".$this->getName()."')");
        }
        $value = $encode ? htmlspecialchars($value) : $value;
        $value = ($charset == 'ISO-8859') ? utf8_encode($value) : $value;
	
	//если более 2 пробелов то в документе они выводятся как 1 пробел!!!
	//пробелы можно вставлять тегом <text:s text:c="<количество пробелов>"/></text:span>
	$matches = null;
	preg_match ("#\ {2,}#", $value , $matches);
	if($matches){
	    for($i=0;$i<count($matches);$i++){
	      $len = strlen($matches[$i]);
	      $value = str_replace($matches[$i], "<text:s text:c=\"{$len}\"/>", $value);
	    }
	}
	
	//вставляем корректные переводы строк:
	$value = str_replace("\n", "<text:line-break/>", $value);
	
	
        $this->vars[$var_name] = $value;
	
	
	//echo "<pre>dump setVars {$this->segment_name} :";
	//echo $var_name." = ".$this->vars[$var_name] ;
	//echo "</pre>";
	//$this->xml = str_replace(array_keys($this->vars), array_values($this->vars), $this->contentXml);
        
	return $this;
    }
    

    public function setImage($key, $value, $width=0, $height=0){
	global $ODF2_PIXEL_TO_CM;
	
	$this->_add_image_file($value);
	
        $filename = strtok(strrchr($value, '/'), '/.');
        $file = substr(strrchr($value, '/'), 1);
	
	if(!$width && !$height){
	    $size = @getimagesize($value);
	    if ($size === false){
		throw new OdfException("Invalid image");
	    }
	    list ($width, $height) = $size;
	    $width  *= $ODF2_PIXEL_TO_CM;
	    $height *= $ODF2_PIXEL_TO_CM;
	}
	
	$width   = round($width,3);
	$height  = round($height,3);
        
	$xml =  "<draw:frame draw:style-name=\"fr1\" "
	       ."draw:name=\"$filename\" text:anchor-type=\"char\" "
	       ."svg:x=\"0.000cm\" svg:y=\"0.000cm\" svg:width=\"{$width}cm\" svg:height=\"{$height}cm\" "
	       ."draw:z-index=\"3\">"
	       ."<draw:image xlink:href=\"Pictures/$file\" xlink:type=\"simple\" xlink:show=\"embed\" xlink:actuate=\"onLoad\"/>"
	       ."</draw:frame>";

        $this->setVars($key, $xml, false);
        return $this;
    }
    

    public function getXmlParsed(){
        return $this->parsedxml;
    }
    public function clear_parsedxml(){
	$this->parsedxml = '';
	if( /*$this->use_temp &&*/ $this->level > 0 ){
	    if($this->parsedxml_temp_file_d){
		fclose($this->parsedxml_temp_file_d);
		$this->parsedxml_temp_file_d = 0;
	    }
	    if(file_exists($this->parsedxml_temp_file_path)){
		unlink($this->parsedxml_temp_file_path);
	    }
	    $this->use_temp = 0;
	}
    }
    public function clear_parsedxml_children(){
        foreach ($this->segments as $seg){
	    $seg->clear_parsedxml();
	    $seg->clear_parsedxml_children();
	}
    }
    

    public function setSegment($segment_name){
        if (!array_key_exists($segment_name, $this->segments)){
	    my_var_dump_html2("\$this->segments",$this->segments);
            throw new OdfException("'$segment_name' segment not found in the document (current segment '".$this->getName()."')");
        }
        return $this->segments[$segment_name];
    }
    
    public function mergeSegment(Odf &$segment){
	
        $this->merge();
        /************
	if (! array_key_exists($segment->getName(), $this->segments)){
            throw new OdfException($segment->getName() . 'cannot be parsed, has it been set yet ?');
        }
        $string = $segment->getName();
		// $reg = '@<text:p[^>]*>\[!--\sBEGIN\s' . $string . '\s--\](.*)\[!--.+END\s' . $string . '\s--\]<\/text:p>@smU';
	$reg = '@\[!-- BEGIN ' . $string . ' --\](.*)\[!-- END ' . $string . ' --\]@smU';
        $this->parsedxml = preg_replace($reg, $segment->getXmlParsed(), $this->xml);
        
        ***********/
	return $this;
    }
    
    protected function _remove_begin_end_delimiter($xml){
	
	$block_name = $this->segment_name;
		
	//v1
	//$reg = "#<table\:table-row[^>]*>.{1,200}\[!--\sBEGIN\s$this->name\s--\](.*)\[!--\sEND\s$this->name\s--\].*<\/table\:table-row>#Usm";
	
	//v2
	//$reg = "#^\[!--\sBEGIN\s{$block_name}\s--\](.*)\[!--\sEND\s{$block_name}\s--\]$#Usm";
	//$this->parsedxml = preg_replace($reg, '$1', $this->parsedxml);
	
	//v3
	$reg = "#\[!-- BEGIN {$block_name} --\]#Usm";
	//if(!preg_match($reg,$xml)) echo "<h4>$reg</h4><code>{$xml}</code>";
	$xml = preg_replace($reg, '', $xml);
	
	$reg = "#\[!-- END {$block_name} --\]#Usm";
	//if(!preg_match($reg,$xml)) echo "<h4>$reg</h4><code>{$xml}</code>";
	$xml = preg_replace($reg, '', $xml);
	
	return $xml;
    }
    
    public function merge(){
	if($this->is_hidden()) return $this->parsedxml = "";
	
	//$aaa = array_values($this->vars);
	//echo "<pre>dump:";
	//var_dump($aaa);
	//echo "</pre>";
	
	$xml = $this->_remove_begin_end_delimiter($this->xml);
	
	
	//echo "<pre>\n-----------------------------------------\n";
	//echo "dump {$this->segment_name} :";
	//echo "\n".htmlspecialchars($this->xml)."\n";
	//var_dump($this->vars);
	//echo "\n===================================\n";
	//echo "</pre>";
	
	
	$hasChildren_with_temp = 0;
	if ( $this->hasChildren() ){
	    $hasChildren_with_temp = $this->hasChildren_with_temp();
	    if( $hasChildren_with_temp==0 ){
		//тут в дочерних блоках не используется временный файл
		$xml = str_replace(array_keys($this->vars), array_values($this->vars), $xml);
		foreach ($this->segments as $seg){
		    $seg_parsedxml = $seg->getXmlParsed();
		    if(!$seg_parsedxml){
			$seg->merge();
			$seg_parsedxml = $seg->getXmlParsed();
		    }
		    $seg->clear_parsedxml();
		    $xml = str_replace($seg->xml, $seg_parsedxml, $xml);
		}
		
		$this->parsedxml .= $xml;
	    }else{
		
		//тут используется временный файл, поэтому сохраняем все содержимое тоже в файл
		//1. сохраняем начало текущего блока
		$this->save();  //сохраняем то что напарсили ранее
		
		//2. вычисляем порядок следования дочерних блоков
		if(count($this->segments_order)==0){
		    //если порядок следования блоков ещё не вычисляли
		    $i = 0;
		    foreach ($this->segments as $seg){
			$is_use_temp = $seg->use_temp;
			$pos = strpos($xml,$seg->xml);
			
			$arr = array('use_temp'=>$is_use_temp,'pos'=>$pos,'name'=>$seg->getName());
			
			$this->segments_order[$i++] = $arr;
			
		    }
		    sort_arr_segments_info($this->segments_order);
		}
		
		
		//3. теперь подставляем значения переменных 
		$xml = str_replace(array_keys($this->vars), array_values($this->vars), $xml); //значения переменных подставляем только после определения позиций
		
		//4. загружаем содержимое дочерних блоков по порядку в текущий временный файл
		for($i=0;$i<count($this->segments_order);$i++){
		    $arr = $this->segments_order[$i];
		    //echo "<h2>{$arr['name']}</h2>";
		    $seg = $this->segments[$arr['name']];
		    if($arr['use_temp']){
			$pos = strpos($xml,$seg->xml);
			$this->parsedxml .= substr($xml,0,$pos); //то что распарсили до этого
			$this->save();
			$this->_add_sub_segment($seg);
			$xml = substr($xml,$pos + strlen($seg->xml));
		    }else{
			$pos = strpos($xml,$seg->xml);
			$this->parsedxml .= substr($xml,0,$pos);
			$seg_parsedxml = $seg->getXmlParsed();
			if(!$seg_parsedxml){
			    $seg->merge();
			    $seg_parsedxml = $seg->getXmlParsed();
			}
			$seg->clear_parsedxml();
			$this->parsedxml .= $seg_parsedxml;
			$xml = substr($xml,$pos + strlen($seg->xml));
		    }
		}
		$this->parsedxml = $xml;
                $this->save();
	    }
	}else{
	    $xml = str_replace(array_keys($this->vars), array_values($this->vars), $xml);
	    $this->parsedxml .= $xml;
	}
	
        
	
        if($hasChildren_with_temp){
	    $this->save();
	}
        return;
    }
    

    protected function _first_save(){
        $this->use_temp = 1;
        $this->parsedxml_temp_file_d = fopen($this->parsedxml_temp_file_path,'w');
    }
    
    public function save(){
	if( !$this->use_temp ) $this->_first_save();
	fwrite($this->parsedxml_temp_file_d,$this->parsedxml);
	//echo "<pre><code>write open: {$this->parsedxml_temp_file_path}</code>\n";
	//echo "<code>write data: {$this->parsedxml}</code></pre>";
	$this->parsedxml = '';
    }
    
    protected function _close_temp(){
	if($this->use_temp && $this->parsedxml_temp_file_d){
	    $this->use_temp = 0;
	    fclose($this->parsedxml_temp_file_d);
	    $this->parsedxml_temp_file_d = 0;
	}
    }
    
    protected function _add_sub_segment(&$seg){
	$this->save();
	$seg->_close_temp();
	$f = fopen($seg->parsedxml_temp_file_path,'r');
	while (!feof($f)) {
	    $buff = fread($f,1024*1024*1);
	    fwrite($this->parsedxml_temp_file_d,$buff);
	}
	fclose($f);
    }
    

    public function saveToDisk($to_file = null){
	global $argv;
	if(!$to_file) $to_file = $argv[0].".out.odt";
	if($this->level!=0) throw new OdfException("Invalid request saveToDisk($to_file) with child block");
	
	//$this->clear_parsedxml_children();
	
	$this->get_odf_meta()->save();
	
	if(!$this->use_temp){
	    $xmlparsed = $this->getXmlParsed();
	    if(!$xmlparsed){
		$this->merge();
		$xmlparsed = $this->getXmlParsed();
	    }
	    
	    $xml_file = "{$this->config->temppath}/content.xml";
	    $fp = fopen($xml_file, 'w');
	    fwrite($fp, $xmlparsed);
	    fclose($fp);
	    
	}else{
	    $this->clear_parsedxml_children();
	    $this->_close_temp();  //закрываем content.xml
	    clear_temp_files($this->config->temppath);
	}

        $temp_path = $this->config->temppath;
	$cmd = "7z a -tzip \"{$to_file}\" \"{$temp_path}/*\" -r";
	//echo "<h3>temp_path: $cmd</h3>";
	exec( $cmd );
    }
    
    public function exportAsAttachedFile(){
	//temp function fo run examples
	global $argv;
	$file = $argv[0];
	$this->saveToDisk($file.".out.odt");
    }   

}

function clear_temp_files($path){
    if($handler = opendir($path)) { 
            while (($sub = readdir($handler)) !== FALSE) { 
                if (substr($sub,0,5) == "temp_" ) { 
                    if(is_file($path."/".$sub)) { 
			unlink($path."/".$sub);
                    }
                } 
            } 
            closedir($handler); 
    }
}

function sort_arr_segments_info(&$arr){
    for($i=0;$i<count($arr);$i++){
	for($j=$i+1;$j<count($arr);$j++){
	    if($arr[$i]['pos'] > $arr[$j]['pos']){
		$tmp = $arr[$i];
		$arr[$i] = $arr[$j];
		$arr[$j] = $tmp;
	    }
	}
    }
}

function rrmdir($dir) {
   if (is_dir($dir)) {
     $objects = scandir($dir);
     foreach ($objects as $object) {
       if ($object != "." && $object != "..") {
         if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object);
       }
     }
     reset($objects);
     rmdir($dir);
   }
}

