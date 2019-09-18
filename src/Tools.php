<?php

namespace NFePHP\MDFe;

/**
 * Classe principal para a comunicação com a SEFAZ
 *
 * @category  Library
 * @package   nfephp-org/sped-mdfe
 * @copyright 2008-2016 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3
 * @link      http://github.com/nfephp-org/sped-mdfe for the canonical source repository
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 */

use NFePHP\MDFe\Common\Tools AS BaseTools;
use NFePHP\Common\DateTime\DateTime;
use NFePHP\Common\Strings;
use NFePHP\Common\Exception;
use NFePHP\Common\DOMImproved as Dom;
use NFePHP\Common\Dom\ValidXsd;
use NFePHP\MDFe\Auxiliar\Response;
use NFePHP\MDFe\Auxiliar\Identify;
use NFePHP\Common\UFList;
use NFePHP\Common\Signer;
use NFePHP\MDFe\Exception\DocumentsException;

class Tools extends BaseTools{

    /**
     * addProtocolo
     * Adiciona o protocolo de autorização de uso da MDFe
     * NOTA: exigência da SEFAZ, a MDFe somente é válida com o seu respectivo protocolo
     *
     * @param  string  $pathMDFefile
     * @param  string  $pathProtfile
     * @param  boolean $saveFile
     * @return string
     * @throws Exception\RuntimeException
     */
    public function addProtocolo($pathMDFefile = '', $pathProtfile = '', $saveFile = false){
        //carrega a MDFe
        $docmdfe = new Dom('1.0', 'UTF-8');
        if (file_exists($pathMDFefile)) {
            //carrega o XML pelo caminho do arquivo informado
            $docmdfe->loadXMLFile($pathMDFefile);
        } else {
            //carrega o XML pelo conteúdo
            $docmdfe->loadXMLString($pathMDFefile);
        }
        $nodemdfe = $docmdfe->getNode('MDFe', 0);
        if ($nodemdfe == '') {
            $msg = "O arquivo indicado como MDFe não é um xml de MDFe!";
            throw new Exception\RuntimeException($msg);
        }
        if ($docmdfe->getNode('Signature') == '') {
            $msg = "O MDFe não está assinado!";
            throw new Exception\RuntimeException($msg);
        }
        //carrega o protocolo
        $docprot = new Dom();
        if (file_exists($pathMDFefile)) {
            //carrega o XML pelo caminho do arquivo informado
            $docprot->loadXMLFile($pathProtfile);
        } else {
            //carrega o XML pelo conteúdo
            $docprot->loadXMLString($pathProtfile);
        }
        $nodeprots = $docprot->getElementsByTagName('protMDFe');
        if ($nodeprots->length == 0) {
            $msg = "O arquivo indicado não contêm um protocolo de autorização!";
            throw new Exception\RuntimeException($msg);
        }
        //carrega dados da MDFe
        $tpAmb = $docmdfe->getNodeValue('tpAmb');
        $anomes = date(
            'Ym',
            DateTime::convertSefazTimeToTimestamp($docmdfe->getNodeValue('dhEmi'))
        );
        $infMDFe = $docmdfe->getNode("infMDFe", 0);
        $versao = $infMDFe->getAttribute("versao");
        $chaveId = $infMDFe->getAttribute("Id");
        $chaveMDFe = preg_replace('/[^0-9]/', '', $chaveId);
        $digValueMDFe = $docmdfe->getNodeValue('DigestValue');
        //carrega os dados do protocolo
        for ($i = 0; $i < $nodeprots->length; $i++) {
            $nodeprot = $nodeprots->item($i);
            $protver = $nodeprot->getAttribute("versao");
            $chaveProt = $nodeprot->getElementsByTagName("chMDFe")->item(0)->nodeValue;
            $digValueProt = $nodeprot->getElementsByTagName("digVal")->item(0)->nodeValue;
            $infProt = $nodeprot->getElementsByTagName("infProt")->item(0);
            if ($digValueMDFe == $digValueProt && $chaveMDFe == $chaveProt) {
                break;
            }
        }
        if ($digValueMDFe != $digValueProt) {
            $msg = "Inconsistência! O DigestValue do MDFe não combina com o"
                . " do digVal do protocolo indicado!";
            throw new Exception\RuntimeException($msg);
        }
        if ($chaveMDFe != $chaveProt) {
            $msg = "O protocolo indicado pertence a outro MDFe. Os números das chaves não combinam !";
            throw new Exception\RuntimeException($msg);
        }
        //cria a MDFe processada com a tag do protocolo
        $procmdfe = new \DOMDocument('1.0', 'UTF-8');
        $procmdfe->formatOutput = false;
        $procmdfe->preserveWhiteSpace = false;
        //cria a tag mdfeProc
        $mdfeProc = $procmdfe->createElement('mdfeProc');
        $procmdfe->appendChild($mdfeProc);
        //estabele o atributo de versão
        $mdfeProcAtt1 = $mdfeProc->appendChild($procmdfe->createAttribute('versao'));
        $mdfeProcAtt1->appendChild($procmdfe->createTextNode($protver));
        //estabelece o atributo xmlns
        $mdfeProcAtt2 = $mdfeProc->appendChild($procmdfe->createAttribute('xmlns'));
        $mdfeProcAtt2->appendChild($procmdfe->createTextNode($this->urlPortal));
        //inclui a tag MDFe
        $node = $procmdfe->importNode($nodemdfe, true);
        $mdfeProc->appendChild($node);
        //cria tag protMDFe
        $protMDFe = $procmdfe->createElement('protMDFe');
        $mdfeProc->appendChild($protMDFe);
        //estabele o atributo de versão
        $protMDFeAtt1 = $protMDFe->appendChild($procmdfe->createAttribute('versao'));
        $protMDFeAtt1->appendChild($procmdfe->createTextNode($versao));
        //cria tag infProt
        $nodep = $procmdfe->importNode($infProt, true);
        $protMDFe->appendChild($nodep);
        //salva o xml como string em uma variável
        $procXML = $procmdfe->saveXML();
        //remove as informações indesejadas
        $procXML = Strings::clearProtocoledXML($procXML);
        
        return $procXML;
    }

    public function addProtocoloEvent($request, $response){

        $ev = new \DOMDocument('1.0', 'UTF-8');
        
        $ev->preserveWhiteSpace = false;
        
        $ev->formatOutput = false;
        
        $ev->loadXML($request);

        $event = $ev->getElementsByTagName('infEvento')->item(0);

        $ret = new \DOMDocument('1.0', 'UTF-8');
        
        $ret->preserveWhiteSpace = false;
        
        $ret->formatOutput = false;
        
        $ret->loadXML($response);

        $retEv = $ret->getElementsByTagName('retEventoMDFe')->item(0);

        $cStat  = $retEv->getElementsByTagName('cStat')->item(0)->nodeValue;
        
        $xMotivo = $retEv->getElementsByTagName('xMotivo')->item(0)->nodeValue;
        
        $tpEvento = $retEv->getElementsByTagName('tpEvento')->item(0)->nodeValue;
        
        $cStatValids = ['135', '136'];
        
        if ($tpEvento == '110111') {
            $cStatValids[] = '155';
        }

        if (!in_array($cStat, $cStatValids)) {
            throw DocumentsException::wrongDocument(4, "[$cStat] $xMotivo");
        }

        return $this->joinXML(
            $ev->saveXML($event),
            $ret->saveXML($retEv),
            'procEventoMDFe',
            '1.00'
        );

    }

    /**
     * Join the pieces of the source document with those of the answer
     * @param string $first
     * @param string $second
     * @param string $nodename
     * @param string $versao
     * @return string
    */
   
    protected function joinXML($first, $second, $nodename, $versao)
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>"
                . "<$nodename versao=\"$versao\" "
                . "xmlns=\"".$this->urlPortal."\">";
        $xml .= $first;
        $xml .= $second;
        $xml .= "</$nodename>";

        return $xml;
    }

    /**
     * addCancelamento
     * Adiciona a tga de cancelamento a uma MDFe já autorizada
     * NOTA: não é requisito da SEFAZ, mas auxilia na identificação das MDFe que foram canceladas
     *
     * @param  string $pathMDFefile
     * @param  string $pathCancfile
     * @param  bool   $saveFile
     * @return string
     * @throws Exception\RuntimeException
     */
    public function addCancelamento($pathMDFefile = '', $pathCancfile = '', $saveFile = false)
    {
        $procXML = '';
        //carrega a MDFe
        $docmdfe = new Dom();
        if (file_exists($pathMDFefile)) {
            //carrega o XML pelo caminho do arquivo informado
            $docmdfe->loadXMLFile($pathMDFefile);
        } else {
            //carrega o XML pelo conteúdo
            $docmdfe->loadXMLString($pathMDFefile);
        }
        $nodemdfe = $docmdfe->getNode('MDFe', 0);
        if ($nodemdfe == '') {
            $msg = "O arquivo indicado como MDFe não é um xml de MDFe!";
            throw new Exception\RuntimeException($msg);
        }
        $proMDFe = $docmdfe->getNode('protMDFe');
        if ($proMDFe == '') {
            $msg = "O MDFe não está protocolado ainda!!";
            throw new Exception\RuntimeException($msg);
        }
        $chaveMDFe = $proMDFe->getElementsByTagName('chMDFe')->item(0)->nodeValue;
        //$nProtMDFe = $proMDFe->getElementsByTagName('nProt')->item(0)->nodeValue;
        $tpAmb = $docmdfe->getNodeValue('tpAmb');
        $anomes = date(
            'Ym',
            DateTime::convertSefazTimeToTimestamp($docmdfe->getNodeValue('dhEmi'))
        );
        //carrega o cancelamento
        //pode ser um evento ou resultado de uma consulta com multiplos eventos
        $doccanc = new Dom();
        if (file_exists($pathCancfile)) {
            //carrega o XML pelo caminho do arquivo informado
            $doccanc->loadXMLFile($pathCancfile);
        } else {
            //carrega o XML pelo conteúdo
            $doccanc->loadXMLString($pathCancfile);
        }
        $retEvento = $doccanc->getElementsByTagName('retEventoMDFe')->item(0);
        $eventos = $retEvento->getElementsByTagName('infEvento');
        foreach ($eventos as $evento) {
            //evento
            $cStat = $evento->getElementsByTagName('cStat')->item(0)->nodeValue;
            $tpAmb = $evento->getElementsByTagName('tpAmb')->item(0)->nodeValue;
            $chaveEvento = $evento->getElementsByTagName('chMDFe')->item(0)->nodeValue;
            $tpEvento = $evento->getElementsByTagName('tpEvento')->item(0)->nodeValue;
            //$nProtEvento = $evento->getElementsByTagName('nProt')->item(0)->nodeValue;
            //verifica se conferem os dados
            //cStat = 135 ==> evento homologado
            //tpEvento = 110111 ==> Cancelamento
            //chave do evento == chave da NFe
            //protocolo do evento ==  protocolo da NFe
            if ($cStat == '135'
                && $tpEvento == '110111'
                && $chaveEvento == $chaveMDFe
            ) {
                $docmdfe->getElementsByTagName('cStat')->item(0)->nodeValue = '101';
                $docmdfe->getElementsByTagName('xMotivo')->item(0)->nodeValue = 'Cancelamento de MDF-e homologado';
                $procXML = $docmdfe->saveXML();
                //remove as informações indesejadas
                $procXML = Strings::clearProt($procXML);
                if ($saveFile) {
                    $filename = "$chaveMDFe-protMDFe.xml";
                    $this->zGravaFile(
                        'mdfe',
                        $tpAmb,
                        $filename,
                        $procXML,
                        'enviadas'.DIRECTORY_SEPARATOR.'aprovadas',
                        $anomes
                    );
                }
                break;
            }
        }
        return (string) $procXML;
    }


    /**
     * verificaValidade
     *
     * @param  string $pathXmlFile
     * @param  array  $aRetorno
     * @return boolean
     * @throws Exception\InvalidArgumentException
     */
    public function verificaValidade($pathXmlFile = '', &$aRetorno = array())
    {
        $aRetorno = array();
        if (!file_exists($pathXmlFile)) {
            $msg = "Arquivo não localizado!!";
            throw new Exception\InvalidArgumentException($msg);
        }
        //carrega a MDFe
        $xml = Files\FilesFolders::readFile($pathXmlFile);
        $this->oCertificate->verifySignature($xml, 'infMDFe');
        //obtem o chave da MDFe
        $docmdfe = new Dom();
        $docmdfe->loadXMLFile($pathXmlFile);
        $tpAmb = $docmdfe->getNodeValue('tpAmb');
        $chMDFe  = $docmdfe->getChave('infMDFe');
        $this->sefazConsultaChave($chMDFe, $tpAmb, $aRetorno);
        if ($aRetorno['cStat'] != '100') {
            return false;
        }
        return true;
    }

    /**
     * assina
     *
     * @param  string  $xml
     * @param  boolean $saveFile
     * @return string
     * @throws Exception\RuntimeException
     */
    public function assina($xml = '', $saveFile = false)
    {
        return $this->assinaDoc($xml, 'mdfe', 'infMDFe', $saveFile);
    }

    /**
     * sefazEnviaLote
     *
     * @param    string $xml
     * @param    string $tpAmb
     * @param    string $idLote
     * @param    array  $aRetorno
     * @return   string
     * @throws   Exception\InvalidArgumentException
     * @throws   Exception\RuntimeException
     * @internal function zLoadServico (Common\Base\BaseTools)
     */
    public function sefazEnviaLote(
        $aXml,
        $tpAmb = '',
        $idLote = '',
        &$aRetorno = array()
    ) {
        
        if (empty($aXml)) {
            $msg = "Pelo menos uma MDFe deve ser informada.";
            throw new Exception\InvalidArgumentException($msg);
        }

        $ax = [];
        
        foreach ($aXml as $xml) {
            $ax[] = trim(preg_replace("/<\?xml.*?\?>/", "", $xml));
        }

        $sxml = trim(implode("", $ax));

        $siglaUF = $this->config->siglaUF;

        if ($tpAmb == '') {
            $tpAmb = $this->config->tpAmb;
        }
        
        $servico = 'MDFeRecepcao';
        
        $this->servico(
            $servico,
            $siglaUF,
            $tpAmb
        );

        if ($this->urlService == '') {
            $msg = "O envio de lote não está disponível na SEFAZ $siglaUF!!!";
            throw new Exception\RuntimeException($msg);
        }   

        //montagem dos dados da mensagem SOAP
        $request = "<enviMDFe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\"><idLote>$idLote</idLote>$sxml</enviMDFe>";


        $this->isValid($this->urlVersion, $request, 'enviMDFe');
        
        $this->lastRequest = $request;

        $parameters = ['mdfeDadosMsg' => $request];

        $body = "<mdfeDadosMsg xmlns=\"$this->urlNamespace\">$request</mdfeDadosMsg>";

        $this->lastResponse = $this->sendRequest($body, $parameters);
        
        return $this->lastResponse;

    }

    /**
     * sefazConsultaRecibo
     *
     * @param    string $recibo
     * @param    string $tpAmb
     * @param    array  $aRetorno
     * @return   string
     * @throws   Exception\InvalidArgumentException
     * @throws   Exception\RuntimeException
     * @internal function zLoadServico (Common\Base\BaseTools)
     */
    public function sefazConsultaRecibo($recibo = '', $tpAmb = '')
    {
        if ($recibo == '') {
            $msg = "Deve ser informado um recibo.";
            throw new Exception\InvalidArgumentException($msg);
        }
        if ($tpAmb == '') {
            $tpAmb = $this->config->tpAmb;
        }

        $siglaUF = $this->config->siglaUF;
        
        //carrega serviço
        $servico = 'MDFeRetRecepcao';
        $this->servico(
            $servico,
            $siglaUF,
            $tpAmb
        );

        if ($this->urlService == '') {
            $msg = "A consulta de MDFe não está disponível na SEFAZ $siglaUF!!!";
            throw new Exception\RuntimeException($msg);
        }

        $request = "<consReciMDFe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
            . "<tpAmb>$tpAmb</tpAmb>"
            . "<nRec>$recibo</nRec>"
            . "</consReciMDFe>";
        
        $this->isValid($this->urlVersion, $request, 'consReciMDFe');

        $this->lastRequest = $request;

        $parameters = ['mdfeDadosMsg' => $request];

        $body = "<mdfeDadosMsg xmlns=\"$this->urlNamespace\">$request</mdfeDadosMsg>";

        $this->lastResponse = $this->sendRequest($body, $parameters);
        
        return $this->lastResponse;
    }

    /**
     * sefazConsultaChave
     * Consulta o status da MDFe pela chave de 44 digitos
     *
     * @param    string $chave
     * @param    string $tpAmb
     * @return   string
     * @throws   Exception\InvalidArgumentException
     * @throws   Exception\RuntimeException
     * @internal function zLoadServico (Common\Base\BaseTools)
     */
    public function sefazConsultaChave($chave = '', $tpAmb = null){
        $chMDFe = preg_replace('/[^0-9]/', '', $chave);
        
        if (strlen($chMDFe) != 44) {
            $msg = "Uma chave de 44 dígitos da MDFe deve ser passada.";
            throw new Exception\InvalidArgumentException($msg);
        }

        if ($tpAmb == '') {
            $tpAmb = $this->config->tpAmb;
        }

        $siglaUF = UFList::getUFByCode(substr($chave, 0, 2));

        $servico = 'MDFeConsulta';

        $this->servico(
            $servico,
            $siglaUF,
            $tpAmb
        );

        if ($this->urlService == '') {
            $msg = "A consulta de MDFe não está disponível na SEFAZ $siglaUF!!!";
            throw new Exception\RuntimeException($msg);
        }

        $request = "<consSitMDFe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
                . "<tpAmb>$tpAmb</tpAmb>"
                . "<xServ>CONSULTAR</xServ>"
                . "<chMDFe>$chMDFe</chMDFe>"
                . "</consSitMDFe>";
        
        $this->isValid($this->urlVersion, $request, 'consSitMDFe');

        $this->lastRequest = $request;

        $parameters = ['mdfeDadosMsg' => $request];

        $body = "<mdfeDadosMsg xmlns=\"$this->urlNamespace\">$request</mdfeDadosMsg>";
        
        $this->lastResponse = $this->sendRequest($body, $parameters);

        return $this->lastResponse;
    }

    /**
     * sefazStatus
     * Verifica o status do serviço da SEFAZ
     * NOTA : Este serviço será removido no futuro, segundo da Receita/SEFAZ devido
     * ao excesso de mau uso !!!
     *
     * @param    string $siglaUF  sigla da unidade da Federação
     * @param    string $tpAmb    tipo de ambiente 1-produção e 2-homologação
     * @param    array  $aRetorno parametro passado por referencia contendo a resposta da consulta em um array
     * @return   mixed string XML do retorno do webservice, ou false se ocorreu algum erro
     * @throws   Exception\RuntimeException
     * @internal function zLoadServico (Common\Base\BaseTools)
     */
    public function sefazStatus($siglaUF = '', $tpAmb = '')
    {
        if ($tpAmb == '') {
            $tpAmb = $this->config->tpAmb;
        }
        if ($siglaUF == '') {
            $siglaUF = $this->config->siglaUF;
        }

        //carrega serviço
        $servico = 'MDFeStatusServico';

        $this->servico(
            $servico,
            $siglaUF,
            $tpAmb
        );

        if ($this->urlService == '') {
            $msg = "O status não está disponível na SEFAZ $siglaUF!!!";
            throw new Exception\RuntimeException($msg);
        }

        $request = "<consStatServMDFe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
            . "<tpAmb>$tpAmb</tpAmb>"
            . "<xServ>STATUS</xServ></consStatServMDFe>";

        $this->lastRequest = $request;
        
        $parameters = ['mdfeDadosMsg' => $request];

        $body = "<mdfeDadosMsg xmlns=\"$this->urlNamespace\">$request</mdfeDadosMsg>";

        $this->lastResponse = $this->sendRequest($body, $parameters);
        
        return $this->lastResponse;
    }

    /**
     * sefazCancela
     *
     * @param  string $chave
     * @param  string $xJust
     * @param  string $nProt
     * @return string
     * @throws Exception\InvalidArgumentException
     */

    public function sefazCancela(
        $chave = '',
        $xJust = '',
        $nProt = ''
    ) {
            
        $nSeqEvento = 1;

        $tpAmb = $this->config->tpAmb;

        $chMDFe = preg_replace('/[^0-9]/', '', $chave);

        $nProt = preg_replace('/[^0-9]/', '', $nProt);

        $xJust = Strings::replaceSpecialsChars($xJust);

        if (strlen($chMDFe) != 44) {
            $msg = "Uma chave de MDFe válida não foi passada como parâmetro $chMDFe.";
            throw new Exception\InvalidArgumentException($msg);
        }

        if ($nProt == '') {
            $msg = "Não foi passado o numero do protocolo!!";
            throw new Exception\InvalidArgumentException($msg);
        }

        if (strlen($xJust) < 15 || strlen($xJust) > 255) {
            $msg = "A justificativa deve ter pelo menos 15 digitos e no máximo 255!!";
            throw new Exception\InvalidArgumentException($msg);
        }

        $siglaUF = UFList::getUFByCode(substr($chave, 0, 2));

        //estabelece o codigo do tipo de evento CANCELAMENTO
        $tpEvento = '110111';
       
        $tagAdic = "<evCancMDFe><descEvento>Cancelamento</descEvento>"
                . "<nProt>$nProt</nProt><xJust>$xJust</xJust></evCancMDFe>";

        $cOrgao = '';

        $retorno = $this->sefazEvento(
                    $siglaUF,
                    $chMDFe,
                    $tpAmb,
                    $tpEvento,
                    $nSeqEvento,
                    $tagAdic);

        return $retorno;
    }

    /**
     * sefazEncerra
     *
     * @param  string $chave
     * @param  string $tpAmb
     * @param  string $nProt
     * @param  string $cUF
     * @param  string $cMun
     * @param  array  $aRetorno
     * @return string
     * @throws Exception\InvalidArgumentException
     */
    public function sefazEncerra(
        $chave = '',
        $nSeqEvento = '1',
        $nProt = '',
        $cUF = '',
        $cMun = '',
        $dtEnc
    ) {
        
        $tpAmb = $this->config->tpAmb;

        $chMDFe = preg_replace('/[^0-9]/', '', $chave);

        $nProt = preg_replace('/[^0-9]/', '', $nProt);

        if (strlen($chMDFe) != 44) {
            $msg = "Uma chave de MDFe válida não foi passada como parâmetro $chMDFe.";
            throw new Exception\InvalidArgumentException($msg);
        }

        if ($nProt == '') {
            $msg = "Não foi passado o numero do protocolo!!";
            throw new Exception\InvalidArgumentException($msg);
        }

        $siglaUF = UFList::getUFByCode(substr($chave, 0, 2));

        $tpEvento = '110112';

        if ($nSeqEvento == '') {
            $nSeqEvento = '1';
        }

        $dtEnc = (new \DateTime($dtEnc))->format('Y-m-d');

        $tagAdic = "<evEncMDFe><descEvento>Encerramento</descEvento>"
                . "<nProt>$nProt</nProt><dtEnc>$dtEnc</dtEnc><cUF>$cUF</cUF>"
                . "<cMun>$cMun</cMun></evEncMDFe>";

        $cOrgao = '';

        $retorno = $this->sefazEvento(
                    $siglaUF,
                    $chMDFe,
                    $tpAmb,
                    $tpEvento,
                    $nSeqEvento,
                    $tagAdic);

        return $retorno;
    }

    /**
     * sefazIncluiCondutor
     *
     * @param  string $chave
     * @param  string $tpAmb
     * @param  string $nSeqEvento
     * @param  string $xNome
     * @param  string $cpf
     * @param  array  $aRetorno
     * @return string
     * @throws Exception\InvalidArgumentException
     */
    public function sefazIncluiCondutor(
        $chave = '',
        $nSeqEvento = '1',
        $xNome = '',
        $cpf = ''
    ) {
        
        $tpAmb = $this->config->tpAmb;

        $chMDFe = preg_replace('/[^0-9]/', '', $chave);

        if (strlen($chMDFe) != 44) {
            $msg = "Uma chave de MDFe válida não foi passada como parâmetro $chMDFe.";
            throw new Exception\InvalidArgumentException($msg);
        }

        $siglaUF = UFList::getUFByCode(substr($chave, 0, 2));

        //estabelece o codigo do tipo de evento Inclusão de condutor
        $tpEvento = '110114';
        if ($nSeqEvento == '') {
            $nSeqEvento = '1';
        }

        //monta mensagem
        $tagAdic = "<evIncCondutorMDFe><descEvento>Inclusao Condutor</descEvento>"
                . "<condutor><xNome>$xNome</xNome><CPF>$cpf</CPF></condutor></evIncCondutorMDFe>";

        $cOrgao = '';

        $retorno = $this->sefazEvento(
                    $siglaUF,
                    $chMDFe,
                    $tpAmb,
                    $tpEvento,
                    $nSeqEvento,
                    $tagAdic);

        return $retorno;
    }

    /**
     * sefazConsultaNaoEncerrados
     *
     * @param  string $tpAmb
     * @param  string $cnpj
     * @param  array  $aRetorno
     * @return string
     * @throws Exception\RuntimeException
     */
    public function sefazConsultaNaoEncerrados($tpAmb = '', $cnpj = '', &$aRetorno = array())
    {
        if ($tpAmb == '') {
            $tpAmb = $this->aConfig['tpAmb'];
        }
        if ($cnpj == '') {
            $cnpj = $this->aConfig['cnpj'];
        }
        $siglaUF = $this->aConfig['siglaUF'];
        //carrega serviço
        $servico = 'MDFeConsNaoEnc';
        $this->zLoadServico(
            'mdfe',
            $servico,
            $siglaUF,
            $tpAmb
        );
        if ($this->urlService == '') {
            $msg = "O serviço não está disponível na SEFAZ $siglaUF!!!";
            throw new Exception\RuntimeException($msg);
        }
        $cons = "<consMDFeNaoEnc xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
            . "<tpAmb>$tpAmb</tpAmb>"
            . "<xServ>CONSULTAR NÃO ENCERRADOS</xServ><CNPJ>$cnpj</CNPJ></consMDFeNaoEnc>";
        //valida mensagem com xsd
        //if (! $this->zValidMessage($cons, 'mdfe', 'consMDFeNaoEnc', $version)) {
        //    $msg = 'Falha na validação. '.$this->error;
        //    throw new Exception\RuntimeException($msg);
        //}
        //montagem dos dados da mensagem SOAP
        $body = "<mdfeDadosMsg xmlns=\"$this->urlNamespace\">$cons</mdfeDadosMsg>";
        //consome o webservice e verifica o retorno do SOAP
        $retorno = $this->oSoap->send(
            $this->urlService,
            $this->urlNamespace,
            $this->urlHeader,
            $body,
            $this->urlMethod
        );
        $lastMsg = $this->oSoap->lastMsg;
        $this->soapDebug = $this->oSoap->soapDebug;
        $datahora = date('Ymd_His');
        $filename = $siglaUF."_"."$datahora-consNaoEnc.xml";
        $this->zGravaFile('mdfe', $tpAmb, $filename, $lastMsg);
        $filename = $siglaUF."_"."$datahora-retConsNaoEnc.xml";
        $this->zGravaFile('mdfe', $tpAmb, $filename, $retorno);
        //tratar dados de retorno
        $aRetorno = Response::readReturnSefaz($servico, $retorno);
        return (string) $retorno;
    }

    /**
     * zSefazEvento
     *
     * @param    string $siglaUF
     * @param    string $chave
     * @param    string $cOrgao
     * @param    string $tpAmb
     * @param    string $tpEvento
     * @param    string $nSeqEvento
     * @param    string $tagAdic
     * @return   string
     * @throws   Exception\RuntimeException
     * @internal function zLoadServico (Common\Base\BaseTools)
     */
    protected function sefazEvento(
        $siglaUF = '',
        $chave = '',
        $tpAmb = '',
        $tpEvento = '',
        $nSeqEvento = '1',
        $tagAdic = ''
    ) {
        
        if ($tpAmb == '') {
            $tpAmb = $this->config->tpAmb;
        }

        $servico = 'MDFeRecepcaoEvento';

        $this->servico(
            $servico,
            $siglaUF,
            $tpAmb
        );

        if ($this->urlService == '') {
            $msg = "A recepção de eventos não está disponível na SEFAZ $siglaUF!!!";
            throw new Exception\RuntimeException($msg);
        }

        $aRet = $this->tpEv($tpEvento);

        $aliasEvento = $aRet['alias'];

        $cnpj = $this->config->cnpj;

        $dhEvento = date('c');

        $sSeqEvento = str_pad($nSeqEvento, 2, "0", STR_PAD_LEFT);

        $eventId = "ID".$tpEvento.$chave.$sSeqEvento;

        $cOrgao = UFList::getCodeByUF($siglaUF);

        $request = "<eventoMDFe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
            . "<infEvento Id=\"$eventId\">"
            . "<cOrgao>$cOrgao</cOrgao>"
            . "<tpAmb>$tpAmb</tpAmb>"
            . "<CNPJ>$cnpj</CNPJ>"
            . "<chMDFe>$chave</chMDFe>"
            . "<dhEvento>$dhEvento</dhEvento>"
            . "<tpEvento>$tpEvento</tpEvento>"
            . "<nSeqEvento>$nSeqEvento</nSeqEvento>"
            . "<detEvento versaoEvento=\"$this->urlVersion\">"
            . "$tagAdic"
            . "</detEvento>"
            . "</infEvento>"
            . "</eventoMDFe>";
        //assinatura dos dados

        $request = Signer::sign(
            $this->certificate,
            $request,
            'infEvento',
            'Id',
            $this->algorithm,
            $this->canonical
        );
        
        $request = Strings::clearXmlString($request, true);
        
        $this->lastRequest = $request;
        
        $parameters = ['mdfeDadosMsg' => $request];

        $this->isValid($this->urlVersion, $request, 'eventoMDFe');

        $body = "<mdfeDadosMsg xmlns=\"$this->urlNamespace\">$request</mdfeDadosMsg>";
        
        $this->lastResponse = $this->sendRequest($body, $parameters);
        
        return $this->lastResponse;
    }

    /**
     * zTpEv
     *
     * @param  string $tpEvento
     * @return array
     * @throws Exception\RuntimeException
     */
    private function tpEv($tpEvento = '')
    {
        //montagem dos dados da mensagem SOAP
        switch ($tpEvento) {
            case '110111':
                //cancelamento
                $aliasEvento = 'CancMDFe';
                $descEvento = 'Cancelamento';
                break;
            case '110112':
                //encerramento
                $aliasEvento = 'EncMDFe';
                $descEvento = 'Encerramento';
                break;
            case '110114':
                //inclusao do condutor
                $aliasEvento = 'EvIncCondut';
                $descEvento = 'Inclusao Condutor';
                break;
            default:
                $msg = "O código do tipo de evento informado não corresponde a "
                . "nenhum evento estabelecido.";
                throw new Exception\RuntimeException($msg);
        }
        return array('alias' => $aliasEvento, 'desc' => $descEvento);
    }

    /**
     * validarXml
     * Valida qualquer xml do sistema MDFe com seu xsd
     * NOTA: caso não exista um arquivo xsd apropriado retorna false
     *
     * @param  string $xml path ou conteudo do xml
     * @return boolean
     */
    public function validarXml($xml = '')
    {
        $aResp = array();
        $schem = Identify::identificar($xml, $aResp);
        if ($schem == '') {
            return true;
        }
        $xsdFile = $aResp['Id'].'_v'.$aResp['versao'].'.xsd';
        $xsdPath = NFEPHP_ROOT.DIRECTORY_SEPARATOR .
            'schemes' .
            DIRECTORY_SEPARATOR .
            $this->aConfig['schemesMDFe'] .
            DIRECTORY_SEPARATOR .
            $xsdFile;
        if (! is_file($xsdPath)) {
            $this->errors[] = "O arquivo XSD $xsdFile não foi localizado.";
            return false;
        }
        if (! ValidXsd::validar($aResp['xml'], $xsdPath)) {
            $this->errors[] = ValidXsd::$errors;
            return false;
        }
        return true;
    }
}
