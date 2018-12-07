<?php 

namespace NFePHP\MDFe;

/**
 * Converts MDFe from text format to xml
 * @category  API
 * @package   NFePHP\MDFe
 * @copyright NFePHP Copyright (c) 2008-2017
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Marlon O. Barbosa <marlon.academi@gmail.com>
 * @link      https://github.com/Focus599Dev/sped-mdfe for the canonical source repository
 */

use NFePHP\Common\Strings;
use NFePHP\MDFe\Exception\DocumentsException;  
use NFePHP\MDFe\Factories\Parser;	

class Convert {
    
    public $txt;
    
    public $dados;
    
    public $numMDFe = 1;
    
    public $notas;
    
    public $layouts = [];
    
    public $xmls = [];

    /**
     * Constructor method
     * @param string $txt
    */
    public function __construct($txt = ''){
        if (!empty($txt)) {
            $this->txt = trim($txt);
        }
    }

    /**
     * Convert all MDFe in XML, one by one
     * @param string $txt
     * @return array
     * @throws \NFePHP\MDFe\Exception\DocumentsException
    */
    public function toXml($txt = ''){
        
        if (!empty($txt)) {
            $this->txt = trim($txt);
        }
        
        $txt = Strings::removeSomeAlienCharsfromTxt($this->txt);
        
        if (!$this->isMDFe($txt)) {

            throw DocumentsException::wrongDocument(12, '');

        }

        $this->notas = $this->sliceNotas($this->dados);

        $this->checkQtdMDFe();

        $this->validManifetos();

        $i = 0;

        foreach ($this->notas as $nota) {

            $version = $this->layouts[$i];

            $parser = new Parser($version);
            
            $this->xmls[] = $parser->toXml($nota);

            $i++;
        }
        
        return $this->xmls;
    }

    /**
     * Check if it is an NFe in TXT format
     * @param string $txt
     * @return boolean
    */
    protected function isMDFe($txt){
        
        if (empty($txt)) {
            throw DocumentsException::wrongDocument(15, '');
        }
        
        $this->dados = explode("\n", $txt);
        
        $fields = explode('|', $this->dados[0]); 
        
        if ($fields[0] == 'MANIFESTO') {
            
            $this->numMDFe = (int) $fields[1];
            
            return true;
        }

        return false;
    }

    /**
     * Separate MDFe into elements of an array
     * @param  array $array
     * @return array
    */
    protected function sliceNotas($array){
        
        $aNotas = [];

        $annu = explode('|', $array[0]);

        $numnotas = $annu[1];

        unset($array[0]);

        if ($numnotas == 1) {

            $aNotas[] = $array;

            return $aNotas;
        }

        $iCount = 0;

        $xCount = 0;

        $resp = [];

        foreach ($array as $linha) {

            if (substr($linha, 0, 2) == 'A|') {

                $resp[$xCount]['init'] = $iCount;

                if ($xCount > 0) {
                    $resp[$xCount -1]['fim'] = $iCount;
                }

                $xCount += 1;
            }

            $iCount += 1;
        }

        $resp[$xCount-1]['fim'] = $iCount;

        foreach ($resp as $marc) {

            $length = $marc['fim']-$marc['init'];

            $aNotas[] = array_slice($array, $marc['init'], $length, false);

        }

        return $aNotas;
    }


    /**
     * Verify number of MDFe declared
     * If different throws an exception
     * @throws \NFePHP\MDFe\Exception\DocumentsException
    */
    protected function checkQtdMDFe(){
        $num = count($this->notas);

        if ($num != $this->numMDFe) {

            throw DocumentsException::wrongDocument(13, '');
        }
    }

    /**
     * Valid all MDFe in txt and get layout version for each MDFe
    */
    protected function validManifetos(){

        foreach ($this->notas as $nota) {

            $this->loadLayouts($nota);

        }
    }

    /**
     * Read and set all layouts in MDFe
     * @param array $nota
    */
    protected function loadLayouts($nota){
        
        if (empty($nota)) {
            throw DocumentsException::wrongDocument(17, '');
        }

        foreach ($nota as $campo) {
            
            $fields = explode('|', $campo);
            
            if ($fields[0] == 'A') {
                $this->layouts[] = $fields[1];
                break;
            }
        }
    }
}

?>