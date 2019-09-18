<?php 


namespace NFePHP\MDFe\Factories;

/**
 * Classe de conversão do TXT para XML
 *
 * @category  API
 * @package   NFePHP\MDFe
 * @copyright NFePHP Copyright (c) 2008-2017
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    marlon.academi@gmail.com <marlon.academi at gmail dot com>
 * @link      https://github.com/Focus599Dev/sped-mdfe for the canonical source repository
 */

use stdClass;
use NFePHP\Common\Strings;
use NFePHP\MDFe\Make;
use NFePHP\MDFe\Exception\DocumentsException;

class Parser{

	protected $version;

    /**
     * @var array
    */
    protected $structure;

    /**
     * @var Make
    */
    protected $make;

    /**
     * @var stdClass|null
    */
    protected $stdide;

    /**
     * @var stdClass|null
    */
    protected $stdEmit;

    /**
     * @var stdClass
    */
    protected $stdinfCTe;

    /**
     * @var stdClass
    */
    protected $stdinfNFe;

    /**
     * @var stdClass
    */
    protected $stdinfMDFeTransp;

    /**
     * @var stdClass
    */
    protected $stdinfResp;

    /**
     * @var stdClass
    */
    protected $stdrodo;

    /**
     * @var array
    */
    protected $stdinfUnidTransp;

    protected $itemInfMunDescarga = null;

    protected $itemUnidadeTransp = null;

    protected $iteminfUnidCarga = null;

    protected $stdPeri = null;
    
    protected $stdInfEntregaParcial = null;
    
    /**
     * Configure environment to correct MDFe layout
     * @param string $version
    */
    public function __construct($version = '3.00'){
        
        $ver = str_replace('.', '', $version);
        
        $path = realpath(__DIR__ . "/../../config/txtstructure$ver.json");
        
        $this->structure = json_decode(file_get_contents($path), true);
        
        $this->version = $version;
        
        $this->make = new Make();

    }

    /**
     * Convert txt to XML
     * @param array $nota
     * @return string|null
     */
    public function toXml($nota){
        
        $this->array2xml($nota);

        if ($this->make->montaMDFe()) {

            return $this->make->getXML();

        }

        return null;

    }

    /**
     * Converte txt array to xml
     * @param array $nota
     * @return void
     */
    protected function array2xml($nota){

        foreach ($nota as $lin) {
            
            $fields = explode('|', $lin);
            
            if (empty($fields)) {
                continue;
            }
            
            $metodo = strtolower(str_replace(' ', '', $fields[0])).'Entity';

            if (!method_exists(__CLASS__, $metodo)) {
                //campo não definido
                throw DocumentsException::wrongDocument(16, $lin);
            }

            $struct = $this->structure[strtoupper($fields[0])];

            $std = $this->fieldsToStd($fields, $struct);

            $this->$metodo($std);
        }
    }



    /**
     * Creates stdClass for all tag fields
     * @param array $dfls
     * @param string $struct
     * @return stdClass
    */
    protected static function fieldsToStd($dfls, $struct){
        $sfls = explode('|', $struct);
        
        $len = count($sfls)-1;
        
        $std = new \stdClass();

        for ($i = 1; $i < $len; $i++) {
            $name = $sfls[$i];
            
            if (isset($dfls[$i]))
                $data = $dfls[$i];
            else 
                $data = '';

            if (!empty($name)) {

                $std->$name = $data;
            }
        }

        return $std;

    }

    /**
     * Create tag infMDFe [A]
     * A|versao|Id|
     * @param stdClass $std
     * @return void
    */
    protected function aEntity($std){
        $this->make->taginfMDFe($std);
    }

    /**
     * Create tag ide [B]
     * B|cUF|tpAmb|tpEmit|mod|serie|nMDF|cMDF|modal|dhEmi|tpEmis|procEmi|verProc|UFIni|UFFim|tpTransp|dhIniViagem|dhIniViagem|
     *
     * @param stdClass $std
     * @return void
     */
    protected function bEntity($std){
        $this->stdide = $std;
        
    }

     /**
     * Create tag infMunCarrega [B01]
     * B01|cMunCarrega|xMunCarrega|
     *
     * @param stdClass $std
     * @return void
     */
    protected function b01Entity($std){
        
        $this->stdide->infMunCarrega[] = $std;

    }

    /**
     * Create tag infPercurso [B02]
     * B02|UFPer|
     *
     * @param stdClass $std
     * @return void
     */
    protected function b02Entity($std){
        
        $this->stdide->infPercurso[] = $std;

    }

    /**
     * Load fields for tag emit [C]
     * C|xNome|xFant|IE
     *
     * @param stdClass $std
     * @return void
     */
    protected function cEntity($std){

        if ((array)$this->stdide){
            $this->make->tagide($this->stdide);
        }
        
        $this->stdEmit = $std;
        
        $this->stdEmit->CNPJ = null;
        
        $this->stdEmit->CPF = null;

    }

    /**
     * Load fields for tag emit [C02]
     * C02|CNPJ|
     *
     * @param stdClass $std
     * @return void
     */
    protected function c02Entity($std){
        
        $this->stdEmit->CNPJ = $std->CNPJ;

        $this->buildCEntity();
        
        $this->stdEmit = null;

    }

    /**
     * Load fields for tag emit [C02a]
     * C02a|CPF|
     *
     * @param stdClass $std
     * @return void
     */
    protected function c02aEntity($std){
        
        $this->stdEmit->CPF = $std->CPF;

        $this->buildCEntity();
        
        $this->stdEmit = null;

    }

    /**
     * Create tag emit [C]
     * @return void
    */
    protected function buildCEntity(){

        $this->make->tagemit($this->stdEmit);

    }

    /**
     * Create tag enderEmit [C05]
     * C05|xLgr|nro|xCpl|xBairro|cMun|xMun|CEP|UF|fone|email|
     * @param stdClass $std
     * @return void
     */
    protected function c05Entity($std){

        $this->make->tagenderEmit($std);

    }

    /**
     * Create tag infMunDescarga [E]
     * E|cMunDescarga|xMunDescarga|
     * @param stdClass $std
     * @return void
     */
    protected function eEntity($std){
        
        if ($this->itemInfMunDescarga === null) {
            
            $this->itemInfMunDescarga = 0;
        
        } else {

            $this->itemInfMunDescarga += 1;

        }

        $std->item = $this->itemInfMunDescarga;

        $this->make->tagInfMunDescarga($std);

    }

    /**
     * Create tag infCTe [F]
     * F|chCTe|SegCodBarra|indReentrega|
     * @param stdClass $std
     * @return void
    */
    protected function fEntity($std){

        $std->item = $this->itemInfMunDescarga;

        $this->stdinfCTe = $std;

    }

    /**
     * Create tag infUnidTransp [F01]
     * F01|tpUnidTransp|idUnidTransp|qtdRat|
     * @param stdClass $std
     * @return void
    */
    protected function f01Entity($std){

        $this->stdinfUnidTransp[] = $std;

        $this->itemUnidadeTransp = (count($this->stdinfUnidTransp) -1);

    }

    /**
     * Create tag lacUnidTransp [F02]
     * F02|nLacre|
     * @param stdClass $std
     * @return void
    */
    protected function f02Entity($std){

        $this->stdinfUnidTransp[$this->itemUnidadeTransp]->lacUnidTransp[] = $std;
        
    }

    /**
     * Create tag infUnidCarga [F03]
     * F03|tpUnidCarga|idUnidCarga|qtdRat|
     * @param stdClass $std
     * @return void
    */
    protected function f03Entity($std){

        $this->stdinfUnidTransp[$this->itemUnidadeTransp]->infUnidCarga[] = $std;

        $this->iteminfUnidCarga = (count($this->stdinfUnidTransp[$this->itemUnidadeTransp]->infUnidCarga) -1);
        
    }

    /**
     * Create tag lacUnidCarga [F03]
     * F04|nLacre|
     * @param stdClass $std
     * @return void
    */
    protected function f04Entity($std){

        $this->stdinfUnidTransp[$this->itemUnidadeTransp]->infUnidCarga[$this->iteminfUnidCarga]->lacUnidCarga[] = $std;
        
    }

    /**
     * Create tag peri [F05]
     * F05|nONU|xNomeAE|xClaRisco|grEmb|qTotProd|qVolTipo|
     * @param stdClass $std
     * @return void
    */
    protected function f05Entity($std){

        $this->stdPeri[] = $std;
        
    }

    /**
     * Create tag infEntregaParcial [F06]
     * F06|qtdTotal|qtdParcial|
     * @param stdClass $std
     * @return void
    */
    protected function f06Entity($std){

        $this->stdInfEntregaParcial = $std;
        
    }

    /**
     * Create tags infCTe  [F07]
     * F06|
     * @param stdClass $std
     * @return void
    */
    protected function f99Entity($std){

        $this->make->tagInfCTe($this->stdinfCTe, $this->stdinfUnidTransp, $this->stdPeri, $this->stdInfEntregaParcial);
    
        $this->stdinfCTe =  null;
        
        $this->stdinfUnidTransp =  null;
        
        $this->stdPeri =  null;
        
        $this->stdInfEntregaParcial =  null;

    }

    /**
     * Create tag infNFe [H]
     * H|chNFe|SegCodBarra|indReentrega|
     * @param stdClass $std
     * @return void
    */
    protected function hEntity($std){

        $std->item = $this->itemInfMunDescarga;

        $this->stdinfNFe = $std;

    }

    /**
     * Create tag infNFe [H01]
     * H01|tpUnidTransp|idUnidTransp|qtdRat|
     * @param stdClass $std
     * @return void
    */
    protected function h01Entity($std){

        $this->stdinfUnidTransp[] = $std;

        $this->itemUnidadeTransp = (count($this->stdinfUnidTransp) -1);

    }

    /**
     * Create tag lacUnidTransp [H02]
     * H02|nLacre|
     * @param stdClass $std
     * @return void
    */
    protected function h02Entity($std){

        $this->stdinfUnidTransp[$this->itemUnidadeTransp]->lacUnidTransp[] = $std;

    }

    /**
     * Create tag infUnidCarga [H03]
     * H03|tpUnidCarga|idUnidCarga|qtdRat
     * @param stdClass $std
     * @return void
    */
    protected function h03Entity($std){

        $this->stdinfUnidTransp[$this->itemUnidadeTransp]->infUnidCarga[] = $std;

        $this->iteminfUnidCarga = (count($this->stdinfUnidTransp[$this->itemUnidadeTransp]->infUnidCarga) -1);
        
    }

    /**
     * Create tag lacUnidCarga [H04]
     * H04|nLacre|
     * @param stdClass $std
     * @return void
    */
    protected function h04Entity($std){

        $this->stdinfUnidTransp[$this->itemUnidadeTransp]->infUnidCarga[$this->iteminfUnidCarga]->lacUnidCarga[] = $std;
        
    }

    /**
     * Create tag lacUnidCarga [H05]
     * H05|nONU|xNomeAE|xClaRisco|grEmb|qTotProd|qVolTipo|
     * @param stdClass $std
     * @return void
    */
    protected function h05Entity($std){

        $this->stdPeri[] = $std;
        
    }

     /**
     * Create tags infCTe  [H06]
     * H06|
     * @param stdClass $std
     * @return void
    */
    protected function h99Entity($std){

        $this->make->tagInfNFe($this->stdinfNFe, $this->stdinfUnidTransp, $this->stdPeri, $this->stdInfEntregaParcial);
    
        $this->stdinfNFe =  null;
        
        $this->stdinfUnidTransp =  null;
        
        $this->stdPeri =  null;
        
        $this->stdInfEntregaParcial =  null;

    }

    /**
     * Create tags infMDFeTransp [J]
     * J|chMDFe|indReentrega
     * @param stdClass $std
     * @return void
    */
    protected function jEntity($std){

        $std->item = $this->itemInfMunDescarga;

        $this->stdinfMDFeTransp = $std;

    }

    /**
     * Create tags infUnidTransp [J01]
     * J01|tpUnidTransp|idUnidTransp|
     * @param stdClass $std
     * @return void
    */
    protected function j01Entity($std){

        $this->stdinfUnidTransp[] = $std;

        $this->itemUnidadeTransp = (count($this->stdinfUnidTransp) -1);

    }

    /**
     * Create tags lacUnidTransp [J02]
     * J02|nLacre|
     * @param stdClass $std
     * @return void
    */
    protected function j02Entity($std){

        $this->stdinfUnidTransp[$this->itemUnidadeTransp]->lacUnidTransp[] = $std;

    }

    /**
     * Create tags infUnidCarga [J03]
     * J03|tpUnidCarga|idUnidCarga|qtdRat|
     * @param stdClass $std
     * @return void
    */
    protected function j03Entity($std){

         $this->stdinfUnidTransp[$this->itemUnidadeTransp]->infUnidCarga[] = $std;

        $this->iteminfUnidCarga = (count($this->stdinfUnidTransp[$this->itemUnidadeTransp]->infUnidCarga) -1);

    }

    /**
     * Create tags infUnidCarga [J04]
     * J04|nLacre|
     * @param stdClass $std
     * @return void
    */
    protected function j04Entity($std){

        $this->stdinfUnidTransp[$this->itemUnidadeTransp]->infUnidCarga[$this->iteminfUnidCarga]->lacUnidCarga[] = $std;

    }

    /**
     * Create tags peri [J05]
     * J05|nONU|xNomeAE|xClaRisco|grEmb|qTotProd|qVolTipo|
     * @param stdClass $std
     * @return void
    */
    protected function j05Entity($std){

        $this->stdPeri[] = $std;

    }

    /**
     * Create tags [J06]
     * J06|
     * @param stdClass $std
     * @return void
    */
    protected function j99Entity($std){

        $this->make->tagInfMDFeTransp($this->stdinfMDFeTransp, $this->stdinfUnidTransp, $this->stdPeri, $this->stdInfEntregaParcial);

        $this->stdinfMDFeTransp =  null;
        
        $this->stdinfUnidTransp =  null;
        
        $this->stdPeri =  null;

    }

    /**
     * Create tags seg [K]
     * K|nApol|nAver|
     * @param stdClass $std
     * @return void
    */
    protected function kEntity($std){

        $this->stdinfResp = $std;

    }

    /**
     * Create tags infResp [K01]
     * K01|respSeg|CNPJ|CPF|
     * @param stdClass $std
     * @return void
    */
    protected function k01Entity($std){

        $this->stdinfResp->infResp = $std;

    }

    /**
     * Create tags respSeg [K02]
     * K02|xSeg|CNPJ|
     * @param stdClass $std
     * @return void
    */
    protected function k02Entity($std){

        $this->stdinfResp->infSeg = $std;

    }

    /**
     * Create tags [K03]
     * K03|
     * @param stdClass $std
     * @return void
    */
    protected function k03Entity($std){

        $this->make->tagSeg($this->stdinfResp);

        $this->stdinfResp = null;
    }


    /**
     * Create tags rodo [O01]
     * O01|RNTRC|codAgPorto|
     * @param stdClass $std
     * @return void
    */
    protected function o01Entity($std){

        $this->stdrodo = $std;

    }

    /**
     * Create tags veicTracao [O02]
     * O02|cInt|placa|tara|capKG|capM3|tpRod|tpCar|UF|RENAVAM|
     * @param stdClass $std
     * @return void
    */
    protected function o02Entity($std){

        $this->stdrodo->veicTracao = $std;
        
    }  

    /**
     * Create tags prop [O03]
     * O03|CPF|CNPJ|RNTRC|xNome|IE|UF|tpProp|
     * @param stdClass $std
     * @return void
    */
    protected function o03Entity($std){

        $this->stdrodo->veicTracao->prop[] = $std;
        
    }   

    /**
     * Create tags condutor [O04]
     * O04|xNome|CPF|
     * @param stdClass $std
     * @return void
    */
    protected function o04Entity($std){

        $this->stdrodo->veicTracao->condutor[] = $std;
        
    }

    /**
     * Create tags condutor [O05]
     * O05|cInt|placa|tara|capKG|capM3|tpCar|UF|RENAVAM|
     * @param stdClass $std
     * @return void
    */
    protected function o05Entity($std){

        $this->stdrodo->veicReboque = $std;
        
    }

    /**
     * Create tags condutor [O06]
     * O06|CPF|CNPJ|RNTRC|xNome|IE|UF|tpProp|
     * @param stdClass $std
     * @return void
    */
    protected function o06Entity($std){

        $this->stdrodo->veicReboque->prop[] = $std;
        
    }

    /**
     * Create tags condutor [O07]
     * O07|CIOT|CPF|CNPJ|
     * @param stdClass $std
     * @return void
    */
    protected function o07Entity($std){

        $this->stdrodo->infCIOT[] = $std;
        
    }

    /**
     * Create tags infContratante [O08]
     * O08|CPF|CNPJ|
     * @param stdClass $std
     * @return void
    */
    protected function o08Entity($std){

        $this->stdrodo->infContratante[] = $std;
        
    }

    /**
     * Create tags valePed [P02]
     * P02|CNPJForn|CNPJPg|nCompra|CPFPg|vValePed|
     * @param stdClass $std
     * @return void
    */
    protected function p02Entity($std){

        $this->stdrodo->valePed[] = $std;
        
    }

    /**
     * Create tags valePed [P03]
     * P03|nLacre
     * @param stdClass $std
     * @return void
    */
    protected function p03Entity($std){

        $this->stdrodo->lacRodo[] = $std;
        
    }

    /**
     * Create tags valePed [P04]
     * P04|
     * @param stdClass $std
     * @return void
    */
    protected function P99Entity($std){

        $this->make->tagRodo($this->stdrodo);

        $this->stdrodo = null;
        
    }

    /**
     * Create tags tot [W02]
     * W02|qCTe|qNFe|qMDFe|vCarga|cUnid|qCarga|
     * @param stdClass $std
     * @return void
    */
    protected function w02Entity($std){
        
        $this->make->tagTot($std);

    }

    /**
     * Create tags lacres [X02]
     * X02|nLacre|
     * @param stdClass $std
     * @return void
    */
    protected function x02Entity($std){
        
        $this->make->tagLacres($std);

    }

    /**
     * Create tags lacres [Y02]
     * Y02|CPF|CNPJ|
     * @param stdClass $std
     * @return void
    */
    protected function y02Entity($std){
        
        $this->make->tagautXML($std);

    }

    /**
     * Create tags lacres [Z]
     * Z|infAdFisco|infCpl|
     * @param stdClass $std
     * @return void
    */
    protected function zEntity($std){
        
        $this->make->taginfAdic($std);

    }

    /**
     * Create tags infRespTec [IRT]
     * IRT|
     * @param stdClass $std
     * @return void
    */
    protected function irtEntity($std){

        // make anything

    }

}

?>
