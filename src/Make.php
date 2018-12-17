<?php

namespace NFePHP\MDFe;

/**
 * Classe a construção do xml do Manifesto Eletrônico de Documentos Fiscais (MDF-e)
 * NOTA: Esta classe foi construida conforme estabelecido no
 * Manual de Orientação do Contribuinte
 * Padrões Técnicos de Comunicação do Manifesto Eletrônico de Documentos Fiscais
 * versão 1.00 de Junho de 2012
 *
 * @category  Library
 * @package   nfephp-org/sped-mdfe
 * @name      Make.php
 * @copyright 2009-2016 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3
 * @link      http://github.com/nfephp-org/sped-mdfe for the canonical source repository
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 */

use NFePHP\Common\Keys;
use NFePHP\Common\DOMImproved as Dom;
use NFePHP\Common\Strings;
use stdClass;
use RuntimeException;
use DOMElement;
use DateTime;

class Make{
    /**
     * versao
     * numero da versão do xml da MDFe
     *
     * @var string
     */
    public $versao = '3.00';
    /**
     * mod
     * modelo da MDFe 58
     *
     * @var integer
     */
    public $mod = '58';

    /**
     * Erros
     * @var array
     */
    private $erros;

    /**
     * chave da MDFe
     *
     * @var string
     */
    public $chMDFe = '';

    //propriedades privadas utilizadas internamente pela classe
    /**
     * @type string|\DOMNode
     */
    private $MDFe = '';
    /**
     * @type string|\DOMNode
     */
    private $infMDFe = '';
    /**
     * @type string|\DOMNode
     */
    private $ide = '';
    /**
     * @type string|\DOMNode
     */
    private $emit = '';
    /**
     * @type string|\DOMNode
     */
    private $enderEmit = '';
    /**
     * @type string|\DOMNode
     */
    private $infModal = '';

    /**
     * @type string|\DOMNode
     */
    private $seg;
    /**
     * @type string|\DOMNode
     */
    private $tot = '';
    /**
     * @type string|\DOMNode
     */
    private $infAdic = '';
    /**
     * @type string|\DOMNode
     */
    private $rodo = '';
    /**
     * @type string|\DOMNode
     */
    private $veicTracao = '';
    /**
     * @type string|\DOMNode
     */
    private $aereo = '';
    /**
     * @type string|\DOMNode
     */
    private $trem = '';
    /**
     * @type string|\DOMNode
     */
    private $aqua = '';

    // Arrays
    private $aInfMunCarrega = []; //array de DOMNode
    private $aInfPercurso = []; //array de DOMNode
    private $aInfMunDescarga = []; //array de DOMNode
    private $aInfCTe = []; //array de DOMNode
    private $aInfNFe = []; //array de DOMNode
    private $aInfMDFe = []; //array de DOMNode
    private $aLacres = []; //array de DOMNode
    private $aAutXML = []; //array de DOMNode
    private $aCondutor = []; //array de DOMNode
    private $aReboque = []; //array de DOMNode
    private $aDisp = []; //array de DOMNode
    private $aVag = []; //array de DOMNode
    private $aInfTermCarreg = []; //array de DOMNode
    private $aInfTermDescarreg = []; //array de DOMNode
    private $aInfEmbComb = []; //array de DOMNode
    private $aCountDoc = []; //contador de documentos fiscais

        /**
     * Função construtora cria um objeto DOMDocument
     * que será carregado com o documento fiscal
     */
    public function __construct()
    {
        $this->dom = new Dom('1.0', 'UTF-8');
        
        $this->dom->preserveWhiteSpace = false;
        
        $this->dom->formatOutput = false;

        $this->erros = array();
    }

    /**
     * Returns xml string and assembly it is necessary
     * @return string
     */
    public function getXML(){
        if (empty($this->xml)) {
            $this->montaMDFe();
        }

        return $this->xml;
    }

    /**
     *
     * @return boolean
     */
    public function montaMDFe()
    {
        if (count($this->erros) > 0) {
            return false;
        }
        //cria a tag raiz da MDFe
        $this->zTagMDFe();

        //monta a tag ide com as tags adicionais
        $this->zTagIde();
        //tag ide [4]
        $this->dom->appChild($this->infMDFe, $this->ide, 'Falta tag "infMDFe"');
        //tag enderemit [30]
        $this->dom->appChild($this->emit, $this->enderEmit, 'Falta tag "emit"');
        //tag emit [25]
        $this->dom->appChild($this->infMDFe, $this->emit, 'Falta tag "infMDFe"');
        //tag infModal [41]

        $this->tagInfModal($this->versao);

        $this->zTagRodo();
        
        // criar posteriromente
        // $this->zTagAereo();
        // $this->zTagFerrov();
        // $this->zTagAqua();
        $this->dom->appChild($this->infMDFe, $this->infModal, 'Falta tag "infMDFe"');
        //tag indDoc [44]
        $this->zTagInfDoc();
        //tag tot [68]
        $this->dom->appChild($this->infMDFe, $this->tot, 'Falta tag "infMDFe"');
        //tag lacres [76]
        $this->zTagLacres();
        // tag autXML [137]
        foreach ($this->aAutXML as $aut) {
            $this->dom->appChild($this->infMDFe, $aut, 'Falta tag "infMDFe"');
        }
        //tag infAdic [78]
        $this->dom->appChild($this->infMDFe, $this->infAdic, 'Falta tag "infMDFe"');
        //[1] tag infMDFe (1 A01)
        $this->dom->appChild($this->MDFe, $this->infMDFe, 'Falta tag "MDFe"');
        //[0] tag MDFe
        $this->dom->appendChild($this->MDFe);
        // testa da chave
        $this->zTestaChaveXML($this->dom);
        //convert DOMDocument para string
        $this->xml = $this->dom->saveXML();

        return true;
    }


    /**
     * taginfMDFe
     * Informações da MDFe 1 pai MDFe
     * tag MDFe/infMDFe
     *
     * @param  string $chave
     * @param  string $versao
     *
     * @return DOMElement
     */
    public function taginfMDFe(stdClass $std){
        
        $possible = ['Id', 'versao'];

        $std = $this->equilizeParameters($std, $possible);

        $this->infMDFe = $this->dom->createElement("infMDFe");
        
        $this->infMDFe->setAttribute("Id", $std->Id);
        
        $this->infMDFe->setAttribute("versao", $std->versao);
        
        $this->chMDFe = $std->Id;
        
        $this->versao = $std->versao;

        return $this->infMDFe;
    }

    /**
     * tgaide
     * Informações de identificação da MDFe
     * tag MDFe/infMDFe/ide
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagide(stdClass $std) {

        $possible = [
            'cUF' => '',
            'tpAmb' => '',
            'tpEmit' => '',
            'mod' => '58',
            'serie' => '',
            'nMDF' => '',
            'cMDF' => '',
            'modal' => '',
            'dhEmi' => '',
            'tpEmis' => '',
            'procEmi' => '',
            'verProc' => '',
            'UFIni' => '',
            'UFFim' => '',
            'tpTransp' => '',
            'dhIniViagem' => '',
            'dhIniViagem' => '',
        ];

        $std = $this->equilizeParameters($std, $possible);

        $this->tpAmb = $std->tpAmb;

        if ($std->dhEmi == '') {
            $dateNow = new \DateTime();

            $std->dhEmi = $dateNow->format('c');
        }

        $identificador = '[4] <ide> - ';

        $ide = $this->dom->createElement("ide");
        
        $this->dom->addChild(
            $ide,
            "cUF",
            $std->cUF,
            true,
            $identificador . "Código da UF do emitente do Documento Fiscal"
        );

        $this->dom->addChild(
            $ide,
            "tpAmb",
            $std->tpAmb,
            true,
            $identificador . "Identificação do Ambiente"
        );

        $this->dom->addChild(
            $ide,
            "tpEmit",
            $std->tpEmit,
            true,
            $identificador . "Indicador da tipo de emitente"
        );

        $this->dom->addChild(
            $ide,
            "tpTransp",
            $std->tpTransp,
            false,
            $identificador . "Tipo do Transportador"
        );

        $this->dom->addChild(
            $ide,
            "mod",
            $std->mod,
            true,
            $identificador . "Código do Modelo do Documento Fiscal"
        );

        $this->dom->addChild(
            $ide,
            "serie",
            intval($std->serie),
            true,
            $identificador . "Série do Documento Fiscal"
        );

        $this->dom->addChild(
            $ide,
            "nMDF",
            intval($std->nMDF),
            true,
            $identificador . "Número do Documento Fiscal"
        );

        $this->dom->addChild(
            $ide,
            "cMDF",
            $std->cMDF,
            true,
            $identificador . "Código do numérico do MDF"
        );

        $this->dom->addChild(
            $ide,
            "cDV",
            $std->cDV,
            true,
            $identificador . "Dígito Verificador da Chave de Acesso da NF-e"
        );

        $this->dom->addChild(
            $ide,
            "modal",
            $std->modal,
            true,
            $identificador . "Modalidade de transporte"
        );

        $this->dom->addChild(
            $ide,
            "dhEmi",
            $std->dhEmi,
            true,
            $identificador . "Data e hora de emissão do Documento Fiscal"
        );

        $this->dom->addChild(
            $ide,
            "tpEmis",
            $std->tpEmis,
            true,
            $identificador . "Tipo de Emissão do Documento Fiscal"
        );

        $this->dom->addChild(
            $ide,
            "procEmi",
            $std->procEmi,
            true,
            $identificador . "Processo de emissão"
        );

        $this->dom->addChild(
            $ide,
            "verProc",
            $std->verProc,
            true,
            $identificador . "Versão do Processo de emissão"
        );

        $this->dom->addChild(
            $ide,
            "UFIni",
            $std->UFIni,
            true,
            $identificador . "Sigla da UF do Carregamento"
        );

        $this->dom->addChild(
            $ide,
            "UFFim",
            $std->UFFim,
            true,
            $identificador . "Sigla da UF do Descarregamento"
        );

        if (isset($std->infMunCarrega) && $std->infMunCarrega){

            foreach ($std->infMunCarrega as $stdinfMunCarrega) {
            
                $infMunCarrega = $this->dom->createElement("infMunCarrega");

                $this->dom->addChild(
                    $infMunCarrega,
                    "cMunCarrega",
                    $stdinfMunCarrega->cMunCarrega,
                    true,
                    "Código do Município de Carregamento"
                );
                
                $this->dom->addChild(
                    $infMunCarrega,
                    "xMunCarrega",
                    $stdinfMunCarrega->xMunCarrega,
                    true,
                    "Nome do Município de Carregamento"
                );

                $this->dom->appChild($ide, $infMunCarrega, 'Falta tag "ide"');

            }

        }

        if (isset($std->infPercurso) && $std->infPercurso){

            foreach ($std->infPercurso as $stdinfPercurso) {

                $infPercurso = $this->dom->createElement("infPercurso");

                $this->dom->addChild(
                    $infPercurso,
                    "UFPer",
                    $stdinfPercurso->ufPer,
                    true,
                    "Sigla das Unidades da Federação do percurso"
                );

                $this->dom->appChild($ide, $infPercurso, 'Falta tag "ide"');

            }

        }

        $this->dom->addChild(
            $ide,
            "dhIniViagem",
            $std->dhIniViagem,
            false,
            $identificador . "Data e hora previstos de inicio da viagem"
        );

        $this->dom->addChild(
            $ide,
            "indCanalVerde",
            $std->indCanalVerde,
            false,
            $identificador . "Prestação de serviço participante do projeto Canal Verde"
        );

        $this->mod = $std->mod;

        $this->ide = $ide;

        return $ide;
    }

    /**
     * tagemit
     * Identificação do emitente da MDFe [25] pai 1
     * tag MDFe/infMDFe/emit
     *
     * @param stdClass $std
     *
     * @return DOMElement
     */
    public function tagemit(stdClass $std) {

        $possible = [
            'CNPJ',
            'CPF',
            'IE',
            'xNome',
            'xFant',
        ];

        $std = $this->equilizeParameters($std, $possible);

        $identificador = '[25] <emit> - ';

        $this->emit = $this->dom->createElement("emit");

        if ($std->CNPJ){
            
            $this->dom->addChild($this->emit, "CNPJ", $std->CNPJ, true, $identificador . "CNPJ do emitente");

        } else if ($std->CPF){

            $this->dom->addChild($this->emit, "CPF", $std->CPF, true, $identificador . "CPF do emitente");

        }

        $this->dom->addChild($this->emit, "IE", $std->IE, true, $identificador . "Inscrição Estadual do emitente");
        
        $this->dom->addChild($this->emit, "xNome", $std->xNome, true, $identificador . "Razão Social ou Nome do emitente");
        
        $this->dom->addChild($this->emit, "xFant", $std->xFant, false, $identificador . "Nome fantasia do emitente");

        return $this->emit;

    }

    /**
     * tagenderEmit
     * Endereço do emitente [30] pai [25]
     * tag MDFe/infMDFe/emit/endEmit
     *
     * @param stdClass $std
     * @return DOMElement
     */
    public function tagenderEmit(stdClass $std) {
        $possible = [
            'xLgr',
            'nro',
            'xCpl',
            'xBairro',
            'cMun',
            'xMun',
            'CEP',
            'UF',
            'fone',
            'email'
        ];

        $std = $this->equilizeParameters($std, $possible);

        $identificador = '[30] <enderEmit> - ';

        $this->enderEmit = $this->dom->createElement("enderEmit");

        $this->dom->addChild(
            $this->enderEmit,
            "xLgr",
            $std->xLgr,
            true,
            $identificador . "Logradouro do Endereço do emitente"
        );

        $this->dom->addChild(
            $this->enderEmit,
            "nro",
            $std->nro,
            true,
            $identificador . "Número do Endereço do emitente"
        );

        $this->dom->addChild(
            $this->enderEmit,
            "xCpl",
            $std->xCpl,
            false,
            $identificador . "Complemento do Endereço do emitente"
        );

        $this->dom->addChild(
            $this->enderEmit,
            "xBairro",
            $std->xBairro,
            true,
            $identificador . "Bairro do Endereço do emitente"
        );

        $this->dom->addChild(
            $this->enderEmit,
            "cMun",
            $std->cMun,
            true,
            $identificador . "Código do município do Endereço do emitente"
        );

        $this->dom->addChild(
            $this->enderEmit,
            "xMun",
            $std->xMun,
            true,
            $identificador . "Nome do município do Endereço do emitente"
        );

        $this->dom->addChild(
            $this->enderEmit,
            "CEP",
            $std->CEP,
            true,
            $identificador . "Código do CEP do Endereço do emitente"
        );

        $this->dom->addChild(
            $this->enderEmit,
            "UF",
            $std->UF,
            true,
            $identificador . "Sigla da UF do Endereço do emitente"
        );

        $this->dom->addChild(
            $this->enderEmit,
            "fone",
            $std->fone,
            false,
            $identificador . "Número de telefone do emitente"
        );

        $this->dom->addChild(
            $this->enderEmit,
            "email",
            $std->email,
            false,
            $identificador . "Endereço de email do emitente"
        );

        return $this->enderEmit;
    }

    /**
     * tagInfMunDescarga
     * tag MDFe/infMDFe/infDoc/infMunDescarga
     *
     * @param stdClass $std
     * @return DOMElement
     */
    public function tagInfMunDescarga(stdClass $std) {
            

        $infMunDescarga = $this->dom->createElement("infMunDescarga");

        $possible = [
            'item',
            'cMunDescarga',
            'xMunDescarga'
        ];

        $std = $this->equilizeParameters($std, $possible);
        
        $this->dom->addChild(
            $infMunDescarga,
            "cMunDescarga",
            $std->cMunDescarga,
            true,
            "Código do Município de Descarga"
        );

        $this->dom->addChild(
            $infMunDescarga,
            "xMunDescarga",
            $std->xMunDescarga,
            true,
            "Nome do Município de Descarga"
        );

        $this->aInfMunDescarga[$std->item] = $infMunDescarga;

        return $infMunDescarga;
    }

    /**
     * tagInfCTe
     * tag MDFe/infMDFe/infDoc/infMunDescarga/infCTe
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagInfCTe($stdinfCTe, $stdinfUnidTransp, $stdPeri, $stdInfEntregaParcial) {

        $infCTe = $this->dom->createElement("infCTe");

        $possibleCTe = [
            'chCTe',
            'SegCodBarra',
            'indReentrega',
        ];

        $stdinfCTe = $this->equilizeParameters($stdinfCTe, $possibleCTe);

        $this->dom->addChild(
            $infCTe,
            "chCTe",
            $stdinfCTe->chCTe,
            true,
            "Chave de Acesso CTe"
        );
        
        $this->dom->addChild(
            $infCTe,
            "SegCodBarra",
            $stdinfCTe->SegCodBarra,
            false,
            "Segundo código de barras do CTe"
        );

        $this->dom->addChild(
            $infCTe,
            "indReentrega",
            $stdinfCTe->indReentrega,
            false,
            "Indicador de Reentrega"
        );

        if ($stdinfUnidTransp){

            foreach ($stdinfUnidTransp as $key => $std) {

                $infUnidTransp = $this->dom->createElement("infUnidTransp");

                $this->dom->addChild(
                    $infUnidTransp,
                    "tpUnidTransp",
                    $std->tpUnidTransp,
                    true,
                    "Tipo da Unidade de Transporte"
                );

                $this->dom->addChild(
                    $infUnidTransp,
                    "idUnidTransp",
                    $std->idUnidTransp,
                    true,
                    "Identificaçãoda unidade de transporte"
                );

                if (isset($std->lacUnidTransp) && $std->lacUnidTransp){

                    foreach ($std->lacUnidTransp as $stdlacUnidTransp) {

                        $lacUnidTransp = $this->dom->createElement("lacUnidTransp");

                        $this->dom->addChild(
                            $lacUnidTransp,
                            "nLacre",
                            $stdlacUnidTransp->nLacre,
                            true,
                            "Número do lacre"
                        );     

                        $this->dom->appChild($infUnidTransp, $lacUnidTransp, 'Falta tag "infUnidCarga"');                   

                    }

                }

                if (isset($std->infUnidCarga) && $std->infUnidCarga){

                    foreach ($std->infUnidCarga as $key2 => $stdinfUnidCarga) {
                        
                        $infUnidCarga = $this->dom->createElement("infUnidCarga");

                        $this->dom->addChild(
                            $infUnidCarga,
                            "tpUnidCarga",
                            $stdinfUnidCarga->tpUnidCarga,
                            true,
                            "Tipo da Unidade de Carga"
                        );

                        $this->dom->addChild(
                            $infUnidCarga,
                            "idUnidCarga",
                            $stdinfUnidCarga->idUnidCarga,
                            true,
                            "Identificação da Unidade de Carga"
                        );

                        if (isset($stdinfUnidCarga->lacUnidCarga) && $stdinfUnidCarga->lacUnidCarga){

                            foreach ($stdinfUnidCarga->lacUnidCarga as $key3 => $stdlacUnidCarga) {

                                $lacUnidCarga = $this->dom->createElement("lacUnidCarga");

                                $this->dom->addChild(
                                    $lacUnidCarga,
                                    "nLacre",
                                    $stdlacUnidCarga->nLacre,
                                    true,
                                    "Número do lacre"
                                );


                                $this->dom->appChild($infUnidCarga, $lacUnidCarga, 'Falta tag "infUnidCarga"');

                            }

                        }

                        $this->dom->addChild(
                            $infUnidCarga,
                            "qtdRat",
                            $std->qtdRat,
                            false,
                            "Quantidade rateada (Peso,Volume)"
                        );

                        $this->dom->appChild($infUnidTransp, $infUnidCarga, 'Falta tag "infUnidCarga"');

                    }

                }

                $this->dom->addChild(
                    $infUnidTransp,
                    "qtdRat",
                    $std->qtdRat,
                    false,
                    "Quantidade rateada (Peso,Volume)"
                );

                $this->dom->appChild($infCTe, $infUnidTransp, 'Falta tag "infCTe"');

            }
        }

        if ($stdPeri){

            foreach ($stdPeri as $key => $stdperi) {

                $peri = $this->dom->createElement("peri");

                $this->dom->addChild(
                    $peri,
                    "nONU",
                    $stdperi->nONU,
                    true,
                    "Numero ONU/UN"
                );

                $this->dom->addChild(
                    $peri,
                    "xNomeAE",
                    $stdperi->xNomeAE,
                    false,
                    "Nome apropriado para embarque do produto"
                );

                $this->dom->addChild(
                    $peri,
                    "xClaRisco",
                    $stdperi->xClaRisco,
                    false,
                    "Classe ou subclasse/divisão, e risco subsidiário/risco secundario"
                );

                $this->dom->addChild(
                    $peri,
                    "grEmb",
                    $stdperi->grEmb,
                    false,
                    "Grupo de Embalagem"
                );

                $this->dom->addChild(
                    $peri,
                    "qTotProd",
                    $stdperi->qTotProd,
                    true,
                    "Quantidade total por produto"
                );

                $this->dom->addChild(
                    $peri,
                    "qVolTipo",
                    $stdperi->qVolTipo,
                    false,
                    "Quantidade e Tipo de volumes"
                );

                $this->dom->appChild($infCTe, $peri, 'Falta tag "infCTe"');

            }

        }

        if ($stdInfEntregaParcial){

            $infEntregaParcial = $this->dom->createElement("infEntregaParcial");

            $this->dom->addChild(
                $infEntregaParcial,
                "qtdTotal",
                $stdInfEntregaParcial->qtdTotal,
                true,
                "Quantidade total de volumes"
            );

            $this->dom->addChild(
                $infEntregaParcial,
                "qtdParcial",
                $stdInfEntregaParcial->qtdParcial,
                true,
                "Quantidade de volumes N enviados no MDF-e"
            );

            $this->dom->appChild($infCTe, $infEntregaParcial, 'Falta tag "infCTe"');
        }

        $this->aInfCTe[$stdinfCTe->item][] = $infCTe;

        return ( count($this->aInfCTe[$stdinfCTe->item]) - 1 );
    }

    /**
     * tagInfNFe
     * tag MDFe/infMDFe/infDoc/infMunDescarga/infNFe
     *
     * @param  integer $nItem
     * @param  string  $chNFe
     * @param  string  $segCodBarra
     *
     * @return DOMElement
     */
    public function tagInfNFe($tagInfNFe, $stdinfUnidTransp, $stdPeri, $stdInfEntregaParcial) {
        
        $infNFe = $this->dom->createElement("infNFe");

        $this->dom->addChild(
            $infNFe,
            "chNFe",
            $tagInfNFe->chNFe,
            true,
            "Chave de Acesso da NFe"
        );

        $this->dom->addChild(
            $infNFe,
            "SegCodBarra",
            $tagInfNFe->SegCodBarra,
            false,
            "Segundo código de barras da NFe"
        );

        $this->dom->addChild(
            $infNFe,
            "indReentrega",
            $tagInfNFe->indReentrega,
            false,
            "Segundo código de barras da NFe"
        );


        if ($stdinfUnidTransp){

            foreach ($stdinfUnidTransp as $key => $std) {

                $infUnidTransp = $this->dom->createElement("infUnidTransp");

                $this->dom->addChild(
                    $infUnidTransp,
                    "tpUnidTransp",
                    $std->tpUnidTransp,
                    true,
                    "Tipo da Unidade de Transporte"
                );

                $this->dom->addChild(
                    $infUnidTransp,
                    "idUnidTransp",
                    $std->idUnidTransp,
                    true,
                    "Identificaçãoda unidade de transporte"
                );

                if (isset($std->lacUnidTransp) && $std->lacUnidTransp){

                    foreach ($std->lacUnidTransp as $stdlacUnidTransp) {

                        $lacUnidTransp = $this->dom->createElement("lacUnidTransp");

                        $this->dom->addChild(
                            $lacUnidTransp,
                            "nLacre",
                            $stdlacUnidTransp->nLacre,
                            true,
                            "Número do lacre"
                        );                        

                        $this->dom->appChild($infUnidTransp, $lacUnidTransp, 'Falta tag "infUnidCarga"');

                    }

                }

                if (isset($std->infUnidCarga) && $std->infUnidCarga){

                    foreach ($std->infUnidCarga as $key2 => $stdinfUnidCarga) {
                        
                        $infUnidCarga = $this->dom->createElement("infUnidCarga");

                        $this->dom->addChild(
                            $infUnidCarga,
                            "tpUnidCarga",
                            $stdinfUnidCarga->tpUnidCarga,
                            true,
                            "Tipo da Unidade de Carga"
                        );

                        $this->dom->addChild(
                            $infUnidCarga,
                            "idUnidCarga",
                            $stdinfUnidCarga->idUnidCarga,
                            true,
                            "Identificação da Unidade de Carga"
                        );

                        if (isset($stdinfUnidCarga->lacUnidCarga) && $stdinfUnidCarga->lacUnidCarga){

                            foreach ($stdinfUnidCarga->lacUnidCarga as $key3 => $stdlacUnidCarga) {

                                $lacUnidCarga = $this->dom->createElement("lacUnidCarga");

                                $this->dom->addChild(
                                    $lacUnidCarga,
                                    "nLacre",
                                    $stdlacUnidCarga->nLacre,
                                    true,
                                    "Número do lacre"
                                );


                                $this->dom->appChild($infUnidCarga, $lacUnidCarga, 'Falta tag "infUnidCarga"');

                            }

                        }

                        $this->dom->addChild(
                            $infUnidCarga,
                            "qtdRat",
                            $std->qtdRat,
                            false,
                            "Quantidade rateada (Peso,Volume)"
                        );

                        $this->dom->appChild($infUnidTransp, $infUnidCarga, 'Falta tag "infUnidCarga"');

                    }

                }

                $this->dom->addChild(
                    $infUnidTransp,
                    "qtdRat",
                    $std->qtdRat,
                    false,
                    "Quantidade rateada (Peso,Volume)"
                );

                $this->dom->appChild($infNFe, $infUnidTransp, 'Falta tag "infCTe"');

            }
        }

        if ($stdPeri){

            foreach ($stdPeri as $key => $stdperi) {

                $peri = $this->dom->createElement("peri");

                $this->dom->addChild(
                    $peri,
                    "nONU",
                    $stdperi->nONU,
                    true,
                    "Numero ONU/UN"
                );

                $this->dom->addChild(
                    $peri,
                    "xNomeAE",
                    $stdperi->xNomeAE,
                    false,
                    "Nome apropriado para embarque do produto"
                );

                $this->dom->addChild(
                    $peri,
                    "xClaRisco",
                    $stdperi->xClaRisco,
                    false,
                    "Classe ou subclasse/divisão, e risco subsidiário/risco secundario"
                );

                $this->dom->addChild(
                    $peri,
                    "grEmb",
                    $stdperi->grEmb,
                    false,
                    "Grupo de Embalagem"
                );

                $this->dom->addChild(
                    $peri,
                    "qTotProd",
                    $stdperi->qTotProd,
                    true,
                    "Quantidade total por produto"
                );

                $this->dom->addChild(
                    $peri,
                    "qVolTipo",
                    $stdperi->qVolTipo,
                    false,
                    "Quantidade e Tipo de volumes"
                );

                $this->dom->appChild($infNFe, $peri, 'Falta tag "infCTe"');

            }

        }

        $this->aInfNFe[$tagInfNFe->item][] = $infNFe;

        return $infNFe;
    }

    /**
     * tagInfMDFeTransp
     * tag MDFe/infMDFeTransp/infDoc/infMunDescarga/infMDFeTranspTransp
     *
     * @param  integer $nItem
     * @param  string  $chMDFe
     *
     * @return DOMElement
     */
    public function tagInfMDFeTransp($stdinfMDFeTransp, $stdinfUnidTransp, $stdPeri, $stdInfEntregaParcial) {

        $possible = [
            'chMDFe',
            'indReentrega'
        ];

        $std = $this->equilizeParameters($std, $possible);

        $infMDFeTransp = $this->dom->createElement("infMDFeTransp");
        
        $this->dom->addChild(
            $infMDFeTransp,
            "chMDFe",
            $std->chMDFe,
            true,
            "Chave de Acesso da MDFe"
        );

        $this->dom->addChild(
            $infMDFeTransp,
            "indReentrega",
            $std->indReentrega,
            false,
            "Indicador de Reentrega"
        );

        if ($stdinfUnidTransp){

            foreach ($stdinfUnidTransp as $key => $std) {

                $infUnidTransp = $this->dom->createElement("infUnidTransp");

                $this->dom->addChild(
                    $infUnidTransp,
                    "tpUnidTransp",
                    $std->tpUnidTransp,
                    true,
                    "Tipo da Unidade de Transporte"
                );

                $this->dom->addChild(
                    $infUnidTransp,
                    "idUnidTransp",
                    $std->idUnidTransp,
                    true,
                    "Identificaçãoda unidade de transporte"
                );

                if (isset($std->lacUnidTransp) && $std->lacUnidTransp){

                    foreach ($std->lacUnidTransp as $stdlacUnidTransp) {

                        $lacUnidTransp = $this->dom->createElement("lacUnidTransp");

                        $this->dom->addChild(
                            $lacUnidTransp,
                            "nLacre",
                            $stdlacUnidTransp->nLacre,
                            true,
                            "Número do lacre"
                        );     

                        $this->dom->appChild($infUnidTransp, $lacUnidTransp, 'Falta tag "infUnidCarga"');                   

                    }

                }

                if (isset($std->infUnidCarga) && $std->infUnidCarga){

                    foreach ($std->infUnidCarga as $key2 => $stdinfUnidCarga) {
                        
                        $infUnidCarga = $this->dom->createElement("infUnidCarga");

                        $this->dom->addChild(
                            $infUnidCarga,
                            "tpUnidCarga",
                            $stdinfUnidCarga->tpUnidCarga,
                            true,
                            "Tipo da Unidade de Carga"
                        );

                        $this->dom->addChild(
                            $infUnidCarga,
                            "idUnidCarga",
                            $stdinfUnidCarga->idUnidCarga,
                            true,
                            "Identificação da Unidade de Carga"
                        );

                        if (isset($stdinfUnidCarga->lacUnidCarga) && $stdinfUnidCarga->lacUnidCarga){

                            foreach ($stdinfUnidCarga->lacUnidCarga as $key3 => $stdlacUnidCarga) {

                                $lacUnidCarga = $this->dom->createElement("lacUnidCarga");

                                $this->dom->addChild(
                                    $lacUnidCarga,
                                    "nLacre",
                                    $stdlacUnidCarga->nLacre,
                                    true,
                                    "Número do lacre"
                                );


                                $this->dom->appChild($infUnidCarga, $lacUnidCarga, 'Falta tag "infUnidCarga"');

                            }

                        }

                        $this->dom->addChild(
                            $infUnidCarga,
                            "qtdRat",
                            $std->qtdRat,
                            false,
                            "Quantidade rateada (Peso,Volume)"
                        );

                        $this->dom->appChild($infUnidTransp, $infUnidCarga, 'Falta tag "infUnidCarga"');

                    }

                }

                $this->dom->addChild(
                    $infUnidTransp,
                    "qtdRat",
                    $std->qtdRat,
                    false,
                    "Quantidade rateada (Peso,Volume)"
                );

                $this->dom->appChild($infMDFeTransp, $infUnidTransp, 'Falta tag "infCTe"');

            }
        }

        if ($stdPeri){

            foreach ($stdPeri as $key => $stdperi) {

                $peri = $this->dom->createElement("peri");

                $this->dom->addChild(
                    $peri,
                    "nONU",
                    $stdperi->nONU,
                    true,
                    "Numero ONU/UN"
                );

                $this->dom->addChild(
                    $peri,
                    "xNomeAE",
                    $stdperi->xNomeAE,
                    false,
                    "Nome apropriado para embarque do produto"
                );

                $this->dom->addChild(
                    $peri,
                    "xClaRisco",
                    $stdperi->xClaRisco,
                    false,
                    "Classe ou subclasse/divisão, e risco subsidiário/risco secundario"
                );

                $this->dom->addChild(
                    $peri,
                    "grEmb",
                    $stdperi->grEmb,
                    false,
                    "Grupo de Embalagem"
                );

                $this->dom->addChild(
                    $peri,
                    "qTotProd",
                    $stdperi->qTotProd,
                    true,
                    "Quantidade total por produto"
                );

                $this->dom->addChild(
                    $peri,
                    "qVolTipo",
                    $stdperi->qVolTipo,
                    false,
                    "Quantidade e Tipo de volumes"
                );

                $this->dom->appChild($infMDFeTransp, $peri, 'Falta tag "infCTe"');

            }

        }

        $this->aInfMDFe[$stdinfMDFeTransp->item][] = $infMDFeTransp;

        return $infMDFeTransp;

    }

    /**
     * tagSeg
     * tag MDFe/seg
     *
     * @param  integer $nItem
     * @param  string  $chMDFe
     *
     * @return DOMElement
    */
    
    public function tagSeg(stdClass $stdinfResp){

        $seg = $this->dom->createElement("seg");

        if (isset($stdinfResp->infResp) && (array)$stdinfResp->infResp){

            $infResp = $this->dom->createElement("infResp");

            $this->dom->addChild(
                $infResp,
                "respSeg",
                $stdinfResp->infResp->respSeg,
                true,
                "Responsável pelo seguro"
            );

            $this->dom->addChild(
                $infResp,
                "CNPJ",
                $stdinfResp->infResp->CNPJ,
                false,
                "Número do CNPJ do responsável pelo seguro"
            );

            $this->dom->addChild(
                $infResp,
                "CPF",
                $stdinfResp->infResp->CPF,
                false,
                "Número do CPF do responsável pelo seguro"
            );

            $this->dom->appChild($seg, $infResp, 'Falta tag "seg"');

        }

        if (isset($stdinfResp->infSeg) && (array)$stdinfResp->infSeg){

            $infSeg = $this->dom->createElement("infSeg");

            $this->dom->addChild(
                $infSeg,
                "respSeg",
                $stdinfResp->infSeg->xSeg,
                true,
                "Nome da Seguradora"
            );

            $this->dom->addChild(
                $infSeg,
                "CNPJ",
                $stdinfResp->infSeg->CNPJ,
                true,
                "Número do CNPJ da seguradora"
            );

            $this->dom->appChild($seg, $infSeg, 'Falta tag "seg"');   
        }

        $this->dom->addChild(
            $seg,
            "nApol",
            $stdinfResp->nApol,
            false,
            "Número da Apólice"
        );

        $this->dom->addChild(
            $seg,
            "nAver",
            $stdinfResp->nAver,
            false,
            "Número da Averbação"
        );


        $this->seg = $seg;

        return $seg;
    }

    /**
     * tagTot
     * tag MDFe/infMDFe/tot
     *
     * @param  stdClass $std
     *
     * @return DOMElement
     */
    public function tagTot(stdClass $std) {
        
        $possible = [
            'qCTe',
            'qNFe',
            'qMDFe',
            'vCarga',
            'cUnid',
            'qCarga'
        ];

        $std = $this->equilizeParameters($std, $possible);

        $tot = $this->dom->createElement("tot");

        $this->dom->addChild(
            $tot,
            "qCTe",
            $std->qCTe,
            false,
            "Quantidade total de CT-e relacionados no Manifesto"
        );
        $this->dom->addChild(
            $tot,
            "qNFe",
            $std->qNFe,
            false,
            "Quantidade total de NF-e relacionados no Manifesto"
        );
        $this->dom->addChild(
            $tot,
            "qMDFe",
            $std->qMDFe,
            false,
            "Quantidade total de MDF-e relacionados no Manifesto"
        );
        $this->dom->addChild(
            $tot,
            "vCarga",
            $std->vCarga,
            true,
            "Valor total da mercadoria/carga transportada"
        );
        $this->dom->addChild(
            $tot,
            "cUnid",
            $std->cUnid,
            true,
            "Código da unidade de medida do Peso Bruto da Carga / Mercadoria Transportada"
        );
        $this->dom->addChild(
            $tot,
            "qCarga",
            $std->qCarga,
            true,
            "Peso Bruto Total da Carga / Mercadoria Transportada"
        );

        $this->tot = $tot;

        return $tot;
    }

    /**
     * tagLacres
     * tag MDFe/infMDFe/lacres
     *
     * @param  stdClass $std
     *
     * @return DOMElement
     */
    public function tagLacres(stdClass $std) {
        
        $possible = [
            'nLacre'
        ];

        $std = $this->equilizeParameters($std, $possible);

        $lacres = $this->dom->createElement("lacres");
        
        $this->dom->addChild(
            $lacres,
            "nLacre",
            $std->nLacre,
            false,
            "Número do lacre"
        );

        $this->aLacres[] = $lacres;

        return $lacres;
    }

    /**
     * taginfAdic
     * Grupo de Informações Adicionais Z01 pai A01
     * tag MDFe/infMDFe/infAdic (opcional)
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function taginfAdic(stdClass $std) {

        $possible = [
            'infAdFisco',
            'infCpl'
        ];

        $std = $this->equilizeParameters($std, $possible);

        $infAdic = $this->dom->createElement("infAdic");

        $this->dom->addChild(
            $infAdic,
            "infAdFisco",
            $std->infAdFisco,
            false,
            "Informações Adicionais de Interesse do Fisco"
        );

        $this->dom->addChild(
            $infAdic,
            "infCpl",
            $std->infCpl,
            false,
            "Informações Complementares de interesse do Contribuinte"
        );

        $this->infAdic = $infAdic;

        return $infAdic;
    }

    /**
     * tagLacres
     * tag MDFe/infMDFe/autXML
     *
     * Autorizados para download do XML do MDF-e
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagautXML(stdClass $std){
        
        $possible = [
            'CNPJ',
            'CPF'
        ];

        $std = $this->equilizeParameters($std, $possible);

        $autXML = $this->dom->createElement("autXML");

        $this->dom->addChild(
            $autXML,
            "CNPJ",
            $std->CNPJ,
            false,
            "CNPJ do autorizado"
        );

        $this->dom->addChild(
            $autXML,
            "CPF",
            $std->CPF,
            false,
            "CPF do autorizado"
        );

        $this->aAutXML[] = $autXML;

        return $autXML;
    }

    /**
     * tagInfModal
     * tag MDFe/infMDFe/infModal
     *
     * @param  string $versaoModal
     *
     * @return DOMElement
     */
    public function tagInfModal($versaoModal = '3.00')
    {
        $infModal = $this->dom->createElement("infModal");

        $infModal->setAttribute("versaoModal", $versaoModal);

        $this->infModal = $infModal;
        return $infModal;
    }

    /**
     * tagAereo
     * tag MDFe/infMDFe/infModal/aereo
     *
     * @param  string $nac
     * @param  string $matr
     * @param  string $nVoo
     * @param  string $cAerEmb
     * @param  string $cAerDes
     * @param  string $dVoo
     *
     * @return DOMElement
     */
    public function tagAereo(
        $nac = '',
        $matr = '',
        $nVoo = '',
        $cAerEmb = '',
        $cAerDes = '',
        $dVoo = ''
    ) {
        $aereo = $this->dom->createElement("aereo");
        $this->dom->addChild(
            $aereo,
            "nac",
            $nac,
            true,
            "Marca da Nacionalidade da aeronave"
        );
        $this->dom->addChild(
            $aereo,
            "matr",
            $matr,
            true,
            "Marca de Matrícula da aeronave"
        );
        $this->dom->addChild(
            $aereo,
            "nVoo",
            $nVoo,
            true,
            "Número do Vôo"
        );
        $this->dom->addChild(
            $aereo,
            "cAerEmb",
            $cAerEmb,
            true,
            "Aeródromo de Embarque - Código IATA"
        );
        $this->dom->addChild(
            $aereo,
            "cAerDes",
            $cAerDes,
            true,
            "Aeródromo de Destino - Código IATA"
        );
        $this->dom->addChild(
            $aereo,
            "dVoo",
            $dVoo,
            true,
            "Data do Vôo"
        );
        $this->aereo = $aereo;
        return $aereo;
    }

    /**
     * tagTrem
     * tag MDFe/infMDFe/infModal/ferrov/trem
     *
     * @param  string $xPref
     * @param  string $dhTrem
     * @param  string $xOri
     * @param  string $xDest
     * @param  string $qVag
     *
     * @return DOMElement
     */
    public function tagTrem(
        $xPref = '',
        $dhTrem = '',
        $xOri = '',
        $xDest = '',
        $qVag = ''
    ) {
        $trem = $this->dom->createElement("trem");
        $this->dom->addChild(
            $trem,
            "xPref",
            $xPref,
            true,
            "Prefixo do Trem"
        );
        $this->dom->addChild(
            $trem,
            "dhTrem",
            $dhTrem,
            false,
            "Data e hora de liberação do trem na origem"
        );
        $this->dom->addChild(
            $trem,
            "xOri",
            $xOri,
            true,
            "Origem do Trem"
        );
        $this->dom->addChild(
            $trem,
            "xDest",
            $xDest,
            true,
            "Destino do Trem"
        );
        $this->dom->addChild(
            $trem,
            "qVag",
            $qVag,
            true,
            "Quantidade de vagões"
        );
        $this->trem = $trem;
        return $trem;
    }

    /**
     * tagVag
     * tag MDFe/infMDFe/infModal/ferrov/trem/vag
     *
     * @param  string $serie
     * @param  string $nVag
     * @param  string $nSeq
     * @param  string $tonUtil
     *
     * @return DOMElement
     */
    public function tagVag(
        $serie = '',
        $nVag = '',
        $nSeq = '',
        $tonUtil = ''
    ) {
        $vag = $this->dom->createElement("vag");
        $this->dom->addChild(
            $vag,
            "serie",
            $serie,
            true,
            "Série de Identificação do vagão"
        );
        $this->dom->addChild(
            $vag,
            "nVag",
            $nVag,
            true,
            "Número de Identificação do vagão"
        );
        $this->dom->addChild(
            $vag,
            "nSeq",
            $nSeq,
            false,
            "Sequência do vagão na composição"
        );
        $this->dom->addChild(
            $vag,
            "TU",
            $tonUtil,
            true,
            "Tonelada Útil"
        );
        $this->aVag[] = $vag;
        return $vag;
    }

    /**
     * tagAqua
     * tag MDFe/infMDFe/infModal/Aqua
     *
     * @param  string $cnpjAgeNav
     * @param  string $tpEmb
     * @param  string $cEmbar
     * @param  string $nViagem
     * @param  string $cPrtEmb
     * @param  string $cPrtDest
     *
     * @return DOMElement
     */
    public function tagAqua(
        $cnpjAgeNav = '',
        $tpEmb = '',
        $cEmbar = '',
        $nViagem = '',
        $cPrtEmb = '',
        $cPrtDest = ''
    ) {
        $aqua = $this->dom->createElement("Aqua");
        $this->dom->addChild(
            $aqua,
            "CNPJAgeNav",
            $cnpjAgeNav,
            true,
            "CNPJ da Agência de Navegação"
        );
        $this->dom->addChild(
            $aqua,
            "tpEmb",
            $tpEmb,
            true,
            "Código do tipo de embarcação"
        );
        $this->dom->addChild(
            $aqua,
            "cEmbar",
            $cEmbar,
            true,
            "Código da Embarcação"
        );
        $this->dom->addChild(
            $aqua,
            "nViagem",
            $nViagem,
            true,
            "Número da Viagem"
        );
        $this->dom->addChild(
            $aqua,
            "cPrtEmb",
            $cPrtEmb,
            true,
            "Código do Porto de Embarque"
        );
        $this->dom->addChild(
            $aqua,
            "cPrtDest",
            $cPrtDest,
            true,
            "Código do Porto de Destino"
        );
        $this->aqua = $aqua;
        return $aqua;
    }

    /**
     * tagInfTermCarreg
     * tag MDFe/infMDFe/infModal/Aqua/infTermCarreg
     *
     * @param  string $cTermCarreg
     *
     * @return DOMElement
     */
    public function tagInfTermCarreg(
        $cTermCarreg = ''
    ) {
        $infTermCarreg = $this->dom->createElement("infTermCarreg");
        $this->dom->addChild(
            $infTermCarreg,
            "cTermCarreg",
            $cTermCarreg,
            true,
            "Código do Terminal de Carregamento"
        );
        $this->aInfTermCarreg[] = $infTermCarreg;
        return $infTermCarreg;
    }

    /**
     * tagInfTermDescarreg
     * tag MDFe/infMDFe/infModal/Aqua/infTermDescarreg
     *
     * @param  string $cTermDescarreg
     *
     * @return DOMElement
     */
    public function tagInfTermDescarreg(
        $cTermDescarreg = ''
    ) {
        $infTermDescarreg = $this->dom->createElement("infTermDescarreg");
        $this->dom->addChild(
            $infTermDescarreg,
            "cTermCarreg",
            $cTermDescarreg,
            true,
            "Código do Terminal de Descarregamento"
        );
        $this->aInfTermDescarreg[] = $infTermDescarreg;
        return $infTermDescarreg;
    }

    /**
     * tagInfEmbComb
     * tag MDFe/infMDFe/infModal/Aqua/infEmbComb
     *
     * @param  string $cEmbComb
     *
     * @return DOMElement
     */
    public function tagInfEmbComb(
        $cEmbComb = ''
    ) {
        $infEmbComb = $this->dom->createElement("infEmbComb");
        $this->dom->addChild(
            $infEmbComb,
            "cEmbComb",
            $cEmbComb,
            true,
            "Código da embarcação do comboio"
        );
        $this->aInfEmbComb[] = $infEmbComb;
        return $infEmbComb;
    }

    /**
     * tagRodo
     * tag MDFe/infMDFe/infModal/rodo
     *
     * @param  stdClass $stdrodo
     * @return DOMElement
     */
    public function tagRodo($stdrodo) {
        
        $rodo = $this->dom->createElement("rodo");

        $infANTT = $this->dom->createElement("infANTT");

        $isinfANTT = false;

        if (isset($stdrodo->infCIOT) && $stdrodo->infCIOT){

            $infCIOT = $this->dom->createElement("infCIOT");

            $this->dom->addChild(
                $infANTT,
                "RNTRC",
                $stdrodo->RNTRC,
                false,
                "Registro Nacional de Transportadores Rodoviár"
            );

            foreach ($stdrodo->infCIOT as $stdinfCIOT) {
                
                $this->dom->addChild(
                    $infCIOT,
                    "CIOT",
                    $stdinfCIOT->CIOT,
                    true,
                    "Registro Nacional de Transportadores Rodoviár"
                ); 
                
                $this->dom->addChild(
                    $infCIOT,
                    "CPF",
                    $stdinfCIOT->CPF,
                    false,
                    "Número do CPF responsável pela geração do CIOT"
                );                

                $this->dom->addChild(
                    $infCIOT,
                    "CNPJ",
                    $stdinfCIOT->CNPJ,
                    false,
                    "Número do CNPJ responsável pela geração do CIOT"
                ); 

                $this->dom->appChild($infANTT, $infCIOT, 'Falta tag "infANTT"');    

            }

            $isinfANTT = true;
        }

        if (isset($stdrodo->valePed) && $stdrodo->valePed){
            
            $valePed = $this->dom->createElement("valePed");

            foreach ( $stdrodo->valePed as  $stdvalePed) {
                
                $disp = $this->dom->createElement("disp");

                $this->dom->addChild(
                    $disp,
                    "CNPJForn",
                    $stdvalePed->CNPJForn,
                    true,
                    "CNPJ da empresa N fornecedora do Vale-Pedágio"
                );

                $this->dom->addChild(
                    $disp,
                    "CNPJPg",
                    $stdvalePed->CNPJPg,
                    false,
                    "CNPJ do responsável pelo N pagamento do Vale-Ped"
                );

                $this->dom->addChild(
                    $disp,
                    "CPFPg",
                    $stdvalePed->CPFPg,
                    false,
                    "CPF do responsavel pelo N pagamento do CE Vale-Pedagio"
                );

                $this->dom->addChild(
                    $disp,
                    "nCompra",
                    $stdvalePed->nCompra,
                    true,
                    "Número do comprovante de N compra"
                );

                $this->dom->addChild(
                    $disp,
                    "vValePed",
                    $stdvalePed->vValePed,
                    true,
                    "Valor do Vale-Pedagio"
                );

                $this->dom->appChild($valePed, $disp, 'Falta tag "valePed"');    


            }

            $this->dom->appChild($infANTT, $valePed, 'Falta tag "valePed"');    

            $isinfANTT = true;
        }  

        if (isset($stdrodo->infContratante) && $stdrodo->infContratante){

            foreach ($stdrodo->infContratante as $stdinfContratante) {

                $infContratante = $this->dom->createElement("infContratante");

                $this->dom->addChild(
                    $infContratante,
                    "CPF",
                    $stdinfContratante->CPF,
                    false,
                    "Número do CPF do contratante do serviço"
                );

                $this->dom->addChild(
                    $infContratante,
                    "CNPJ",
                    $stdinfContratante->CNPJ,
                    false,
                    "Número do CNPJ do contratante do serviço"
                );

                $this->dom->appChild($infANTT, $infContratante, 'Falta tag "valePed"');    

            }

            $isinfANTT = true;

        }
             

        if ($isinfANTT){
            
            $this->dom->appChild($rodo, $infANTT, 'Falta tag "infANTT"');    

        }

        if (isset($stdrodo->veicTracao) && $stdrodo->veicTracao){

            $veicTracao = $this->dom->createElement("veicTracao");

            $this->dom->addChild(
                $veicTracao,
                "cInt",
                $stdrodo->veicTracao->cInt,
                false,
                "Código interno do veículo"
            );

            $this->dom->addChild(
                $veicTracao,
                "placa",
                $stdrodo->veicTracao->placa,
                true,
                "Placa do veículo"
            );

            $this->dom->addChild(
                $veicTracao,
                "RENAVAM",
                $stdrodo->veicTracao->RENAVAM,
                false,
                "RENAVAM do veículo"
            );

            $this->dom->addChild(
                $veicTracao,
                "tara",
                $stdrodo->veicTracao->tara,
                true,
                "Tara em KG"
            );

            $this->dom->addChild(
                $veicTracao,
                "capKG",
                $stdrodo->veicTracao->capKG,
                false,
                "Capacidade em KG"
            );

            $this->dom->addChild(
                $veicTracao,
                "capM3",
                $stdrodo->veicTracao->capM3,
                false,
                "Capacidade em M3"
            );

            if (isset($stdrodo->veicTracao->prop) && $stdrodo->veicTracao->prop){

                foreach ($stdrodo->veicTracao->prop as $stdprop) {

                    $prop = $this->dom->createElement("prop");

                    $this->dom->addChild(
                        $prop,
                        "CPF",
                        $stdprop->CPF,
                        false,
                        "Número do CPF"
                    );

                    $this->dom->addChild(
                        $prop,
                        "CNPJ",
                        $stdprop->CNPJ,
                        false,
                        "Número do CNPJ"
                    );

                    $this->dom->addChild(
                        $prop,
                        "RNTRC",
                        $stdprop->RNTRC,
                        true,
                        "Registro Nacional dos Transportadores Rodoviário"
                    );

                    $this->dom->addChild(
                        $prop,
                        "xNome",
                        $stdprop->xNome,
                        true,
                        "Razão Social ou Nome do proprietário"
                    );

                    $this->dom->addChild(
                        $prop,
                        "IE",
                        $stdprop->IE,
                        false,
                        "Inscrição Estadual"
                    );

                    $this->dom->addChild(
                        $prop,
                        "UF",
                        $stdprop->UF,
                        false,
                        "UF"
                    );

                    $this->dom->addChild(
                        $prop,
                        "tpProp",
                        $stdprop->tpProp,
                        true,
                        "Tipo Proprietário"
                    );

                    $this->dom->appChild($veicTracao, $prop, 'Falta tag "veicTracao"');    

                }

            }

            if (isset($stdrodo->veicTracao->condutor) && $stdrodo->veicTracao->condutor){

                    foreach ($stdrodo->veicTracao->condutor as $stdcondutor) {

                        $condutor = $this->dom->createElement("condutor");

                        $this->dom->addChild(
                            $condutor,
                            "xNome",
                            $stdcondutor->xNome,
                            true,
                            "Nome do Condutor"
                        );

                        $this->dom->addChild(
                            $condutor,
                            "CPF",
                            $stdcondutor->CPF,
                            true,
                            "CPF do Condutor"
                        );

                        $this->dom->appChild($veicTracao, $condutor, 'Falta tag "veicTracao"');    

                    }

            }

            $this->dom->addChild(
                $veicTracao,
                "tpRod",
                $stdrodo->veicTracao->tpRod,
                false,
                "Tipo de Rodado"
            );

            $this->dom->addChild(
                $veicTracao,
                "tpCar",
                $stdrodo->veicTracao->tpCar,
                false,
                "Tipo de Carroceria"
            );

            $this->dom->addChild(
                $veicTracao,
                "UF",
                $stdrodo->veicTracao->UF,
                false,
                "UF em que veículo está licenciado"
            );

            $this->dom->appChild($rodo, $veicTracao, 'Falta tag "rodo"');    

        }

        if (isset($stdrodo->veicReboque) && $stdrodo->veicReboque){

            $veicReboque = $this->dom->createElement("veicReboque");
            
            $this->dom->addChild(
                $veicReboque,
                "cInt",
                $stdrodo->veicReboque->cInt,
                false,
                "Código interno do veículo"
            );

            $this->dom->addChild(
                $veicReboque,
                "placa",
                $stdrodo->veicReboque->placa,
                true,
                "Placa do veículo"
            );

            $this->dom->addChild(
                $veicReboque,
                "RENAVAM",
                $stdrodo->veicReboque->RENAVAM,
                false,
                "RENAVAM do veículo"
            );

            $this->dom->addChild(
                $veicReboque,
                "tara",
                $stdrodo->veicReboque->tara,
                true,
                "Tara em KG"
            );

            $this->dom->addChild(
                $veicReboque,
                "capKG",
                $veicReboque->capKG,
                false,
                "Capacidade em KG"
            );

            $this->dom->addChild(
                $veicReboque,
                "capM3",
                $stdrodo->veicReboque->capM3,
                false,
                "Capacidade em M3"
            );  
            
            if (isset($stdrodo->veicReboque->prop) && $stdrodo->veicReboque->prop){

                foreach ($stdrodo->veicReboque->prop as $stdprop) {
                    
                    $prop = $this->dom->createElement("prop");

                    $this->dom->addChild(
                        $prop,
                        "CPF",
                        $stdprop->CPF,
                        false,
                        "Número do CPF"
                    );

                    $this->dom->addChild(
                        $prop,
                        "CNPJ",
                        $stdprop->CNPJ,
                        false,
                        "Número do CNPJ"
                    );    

                    $this->dom->addChild(
                        $prop,
                        "RNTRC",
                        $stdprop->RNTRC,
                        true,
                        "Registro Nacional dos Transportadores Rodoviário"
                    ); 

                    $this->dom->addChild(
                        $prop,
                        "xNome",
                        $stdprop->xNome,
                        true,
                        "Razão Social ou Nome do proprietário"
                    );

                    $this->dom->addChild(
                        $prop,
                        "IE",
                        $stdprop->IE,
                        false,
                        "Inscrição Estadual"
                    );

                    $this->dom->addChild(
                        $prop,
                        "UF",
                        $stdprop->UF,
                        false,
                        "UF"
                    );   

                    $this->dom->addChild(
                        $prop,
                        "tpProp",
                        $stdprop->tpProp,
                        false,
                        "Tipo Proprietário"
                    );  

                    $this->dom->appChild($veicReboque, $prop, 'Falta tag "veicReboque"');    

                }

            }                                 

        }        

        $this->dom->addChild(
            $rodo,
            "codAgPorto",
            $stdrodo->codAgPorto,
            false,
            "Código de Agendamento no porto"
        );

        if ( isset($stdrodo->lacRodo) && $stdrodo->lacRodo){

            foreach ($stdrodo->lacRodo as $stdlacRodo) {
                
                $lacRodo = $this->dom->createElement("lacRodo");

                $this->dom->addChild(
                    $lacRodo,
                    "nLacre",
                    $stdlacRodo->nLacre,
                    true,
                    "Lacre"
                );

                $this->dom->appChild($rodo, $lacRodo, 'Falta tag "rodo"');                    

            }

        }

        $this->rodo = $rodo;

        return $rodo;
    }

    /**
     * zTagMDFe
     * Tag raiz da MDFe
     * tag MDFe DOMNode
     * Função chamada pelo método [ monta ]
     *
     * @return DOMElement
     */
    protected function zTagMDFe(){
        
        if (empty($this->MDFe)) {

            $this->MDFe = $this->dom->createElement("MDFe");

            $this->MDFe->setAttribute("xmlns", "http://www.portalfiscal.inf.br/mdfe");

        }

        return $this->MDFe;
    }

    /**
     * Adiciona as tags
     * infMunCarrega e infPercurso
     * a tag ide
     */
    protected function zTagIde(){
        $this->dom->addArrayChild($this->ide, $this->aInfMunCarrega);

        $this->dom->addArrayChild($this->ide, $this->aInfPercurso);
    }

    /**
     * Processa lacres
     */
    protected function zTagLacres(){

        $this->dom->addArrayChild($this->infMDFe, $this->aLacres);

    }

    /**
     * Proecessa documentos fiscais
     */
    protected function zTagInfDoc(){

        $this->aCountDoc = ['CTe'=>0, 'NFe'=>0, 'MDFe'=>0];

        if (! empty($this->aInfMunDescarga)) {

            $infDoc = $this->dom->createElement("infDoc");

            $this->aCountDoc['CTe'] = 0;

            $this->aCountDoc['NFe'] = 0;

            $this->aCountDoc['MDFe'] = 0;

            foreach ($this->aInfMunDescarga as $nItem => $node) {
                
                if (isset($this->aInfCTe[$nItem])) {
                    $this->dom->addArrayChild($node, $this->aInfCTe[$nItem]);
                }
                if (isset($this->aInfNFe[$nItem])) {
                   $this->dom->addArrayChild($node, $this->aInfNFe[$nItem]);
                }
                if (isset($this->aInfMDFe[$nItem])) {
                    $this->dom->addArrayChild($node, $this->aInfMDFe[$nItem]);
                }

                $this->dom->appChild($infDoc, $node, '');
            }


            $this->dom->appChild($this->infMDFe, $infDoc, 'Falta tag "infMDFe"');

        }

    }

    /**
     * Processa modal rodoviario
     */
    protected function zTagRodo(){

        if (!empty($this->rodo)) {

            $this->dom->appChild($this->infModal, $this->rodo, 'Falta tag "infModal"');

        }
    }

    /**
     * Proecessa modal ferroviario
     */
    protected function zTagFerrov()
    {
        if (! empty($this->trem)) {
            $this->dom->addArrayChild($this->trem, $this->aVag);
            $ferrov = $this->dom->createElement("ferrov");
            $this->dom->appChild($ferrov, $this->trem, '');
            $this->dom->appChild($this->infModal, $ferrov, 'Falta tag "infModal"');
        }
    }

    /**
     * Processa modal aereo
     */
    protected function zTagAereo()
    {
        if (! empty($this->aereo)) {
            $this->dom->appChild($this->infModal, $this->aereo, 'Falta tag "infModal"');
        }
    }

    /**
     * Processa modal aquaviário
     */
    protected function zTagAqua()
    {
        if (! empty($this->aqua)) {
            $this->dom->addArrayChild($this->aqua, $this->aInfTermCarreg);
            $this->dom->addArrayChild($this->aqua, $this->aInfTermDescarreg);
            $this->dom->addArrayChild($this->aqua, $this->aInfEmbComb);
            $this->dom->appChild($this->infModal, $this->aqua, 'Falta tag "infModal"');
        }
    }

    /**
     * zTestaChaveXML
     * Remonta a chave da NFe de 44 digitos com base em seus dados
     * Isso é útil no caso da chave informada estar errada
     * se a chave estiver errada a mesma é substituida
     *
     * @param object $dom
     */
    private function zTestaChaveXML($dom)
    {
        $infMDFe = $dom->getElementsByTagName("infMDFe")->item(0);
        $ide = $dom->getElementsByTagName("ide")->item(0);
        $emit = $dom->getElementsByTagName("emit")->item(0);
        $cUF = $ide->getElementsByTagName('cUF')->item(0)->nodeValue;
        $dhEmi = $ide->getElementsByTagName('dhEmi')->item(0)->nodeValue;
        $cnpj = $emit->getElementsByTagName('CNPJ')->item(0)->nodeValue;
        $mod = $ide->getElementsByTagName('mod')->item(0)->nodeValue;
        $serie = $ide->getElementsByTagName('serie')->item(0)->nodeValue;
        $nNF = $ide->getElementsByTagName('nMDF')->item(0)->nodeValue;
        $tpEmis = $ide->getElementsByTagName('tpEmis')->item(0)->nodeValue;
        $cNF = $ide->getElementsByTagName('cMDF')->item(0)->nodeValue;
        $chave = str_replace('MDFe', '', $infMDFe->getAttribute("Id"));
        $tempData = explode("-", $dhEmi);
        
        $dt = new DateTime($dhEmi);

        $chaveMontada = Keys::build(
            $cUF,
            $dt->format('y'),
            $dt->format('m'),
            $cnpj,
            $mod,
            $serie,
            $nNF,
            $tpEmis,
            $cNF
        );

        //caso a chave contida na NFe esteja errada
        //substituir a chave

        if ($chaveMontada != $chave) {

            throw new \Exception("Erro chave não condiz com os parametros");
            
            // $ide->getElementsByTagName('cDV')->item(0)->nodeValue = substr($chaveMontada, -1);
            // $infMDFe = $dom->getElementsByTagName("infMDFe")->item(0);
            // $infMDFe->setAttribute("Id", "MDFe" . $chaveMontada);
            // $this->chMDFe = $chaveMontada;
        }
    }

    /**
     * Includes missing or unsupported properties in stdClass
     * @param stdClass $std
     * @param array $possible
     * @return stdClass
    */
    protected function equilizeParameters(stdClass $std, $possible){
        $arr = get_object_vars($std);
        
        foreach ($possible as $key) {
            if (!array_key_exists($key, $arr)) {
                $std->$key = null;
            }
        }

        return $std;
    }
}
