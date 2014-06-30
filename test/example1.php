<?php

/**
 * Tutoriel file
 * Description : Merging a Segment within an array
 * You need PHP 5.2 at least
 * You need Zip Extension or PclZip library
 *
 * @copyright  GPL License 2008 - Julien Pauli - Cyril PIERRE de GEYER - Anaska (http://www.anaska.com)
 * @license    http://www.gnu.org/copyleft/gpl.html  GPL License
 * @version 1.3
 */


// Make sure you have Zip extension or PclZip library loaded
// First : include the librairy
require_once('../odf.php');

$listeArticles = array(
	array(	'titre' => 'PHP',
		'texte' => 'PHP (sigle de PHP: Hypertext Preprocessor), est un langage de scripts (...)',
	),
	array(	'titre' => 'MySQL',
		'texte' => 'MySQL est un systиme de gestion de base de donnйes (SGDB). Selon le (...)',
	),
	array(	'titre' => 'Apache',
		'texte' => 'Apache HTTP Server, souvent appelй Apache, est un logiciel de serveur (...)',
	),		
);

$odf = new odf("example1.odt");

$odf->setVars('titre', 'Quelques articles de l\'encyclopйdie Wikipйdia');

$tables1 = $odf->tables1;

for($t=0;$t<2;$t++){
	$tables1->setVars('message', 'test table'.$t);
	

	
	$article = $tables1->setSegment('articles');
	foreach($listeArticles AS $element) {
		$article->titreArticle($element['titre']);
		$article->texteArticle($element['texte']);
		
		for($i=0;$i<3;$i++){
			$tables2 = $article->setSegment('tables2');
			$tables2->title('test table2 title '.$i);
			
			$articles2 = $tables2->articles2;
			$j=0;
			foreach($listeArticles AS $element) {
			        $j++;
				$articles2->name($element['titre']);
				$articles2->data("table $t -> data $i $j");
				$articles2->value($element['texte']);
				$articles2->merge();
				$articles2->save();  
			}
			
			$tables2->merge();
		}
		
		
		$article->merge();
		$article->save();  
		
	}
	$tables1->merge();

}

//$tables1->mergeSegment($article);

$odf->merge();

// We export the file
//$odf->exportAsAttachedFile();
 
$odf->saveToDisk("example1.out.odt");

