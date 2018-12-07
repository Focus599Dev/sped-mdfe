<?php 

namespace NFePHP\MDFe\Common;

/**
 * Class base responsible for communication with SEFAZ
 *
 * @category  NFePHP
 * @package   NFePHP\NFe\MDFe\Tools
 * @copyright NFePHP Copyright (c) 2008-2017
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Marlon O. Borbosa
 * @link      https://github.com/Focus599Dev/sped-mdfe for the canonical source repository
 */

use DOMDocument;
use InvalidArgumentException;
use RuntimeException;
use NFePHP\Common\Certificate;
use NFePHP\Common\Signer;
use NFePHP\Common\Strings;
use NFePHP\Common\TimeZoneByUF;
use NFePHP\Common\UFList;
use NFePHP\Common\Validator;
use NFePHP\MDFe\Factories\Header;
use SoapHeader;
use NFePHP\MDFe\Soap\SoapCurl;
use NFePHP\MDFe\Soap\SoapInterface;

class Tools {

	/**
     * config class
     * @var \stdClass
     */
    public $config;

    /**
     * Path to storage folder
     * @var string
    */
    public $pathwsfiles = '';

    /**
     * Path to schemes folder
     * @var string
     */
    public $pathschemes = '';

    /**
     * ambiente
     * @var string
     */
    public $ambiente = 'homologacao';

    /**
     * Environment
     * @var int
    */
    public $tpAmb = 2;

    /**
     * contingency class
     * @var Contingency
    */
    public $contingency;

    /**
     * soap class
     * @var SoapInterface
    */
    public $soap;

    /**
     * Application version
     * @var string
    */
    public $verAplic = '';

    /**
     * last soap request
     * @var string
     */
    public $lastRequest = '';
    /**
     * last soap response
     * @var string
     */
    public $lastResponse = '';
    /**
     * certificate class
     * @var Certificate
     */
    protected $certificate;
    /**
     * Sign algorithm from OPENSSL
     * @var int
     */
    protected $algorithm = OPENSSL_ALGO_SHA1;
    /**
     * Canonical conversion options
     * @var array
     */
    protected $canonical = [true,false,null,null];

    /**
     * Model of MDFE 58
     * @var int
     */
    protected $modelo = 58;

    /**
     * Version of layout
     * @var string
     */
    protected $versao = '3.00';

    /**
     * urlPortal
     * Instância do WebService
     *
     * @var string
     */
    protected $urlPortal = 'http://www.portalfiscal.inf.br/mdfe';

    /**
     * urlcUF
     * @var int
     */
    protected $urlcUF;
    /**
     * urlVersion
     * @var string
     */
    protected $urlVersion = '';
    /**
     * urlService
     * @var string
     */
    protected $urlService = '';
    /**
     * @var string
     */
    protected $urlMethod = '';
    /**
     * @var string
     */
    protected $urlOperation = '';
    /**
     * @var string
     */
    protected $urlNamespace = '';
    /**
     * @var string
     */
    protected $urlAction = '';
    /**
     * @var \SoapHeader | null
     */
    protected $objHeader = null;
    /**
     * @var string
     */
    protected $urlHeader = '';
    /**
     * @var array
     */
    protected $soapnamespaces = [
        'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
        'xmlns:xsd' => "http://www.w3.org/2001/XMLSchema",
        'xmlns:soap' => "http://www.w3.org/2003/05/soap-envelope"
    ];

    /**
     * @var array
     */
    protected $availableVersions = [
        '3.00' => 'PL_MDFe_300_NT022018',
    ];

    /**
     * Constructor
     * load configurations,
     * load Digital Certificate,
     * map all paths,
     * set timezone and
     * and instanciate Contingency::class
     * @param string $configJson content of config in json format
     * @param Certificate $certificate
     */
    public function __construct($configJson, Certificate $certificate){
        $this->pathwsfiles = realpath(
            __DIR__ . '/../../config'
        ).'/';

        //valid config json string
        $this->config = json_decode($configJson);
        
        $this->version($this->config->versao);
        $this->setEnvironmentTimeZone($this->config->siglaUF);
        $this->certificate = $certificate;
        $this->setEnvironment($this->config->tpAmb);
        $this->soap = new SoapCurl($certificate);
        if ($this->config->proxy){
            $this->soap->proxy($this->config->proxy, $this->config->proxyPort, $this->config->proxyUser, $this->config->proxyPass);
        }
    }

    /**
     * Sets environment time zone
     * @param string $acronym (ou seja a sigla do estado)
     * @return void
    */
    public function setEnvironmentTimeZone($acronym){
        date_default_timezone_set(TimeZoneByUF::get($acronym));
    }

    /**
     * Alter environment from "homologacao" to "producao" and vice-versa
     * @param int $tpAmb
     * @return void
     */
    public function setEnvironment($tpAmb = 2){
        if (!empty($tpAmb) && ($tpAmb == 1 || $tpAmb == 2)) {
            $this->tpAmb = $tpAmb;
            $this->ambiente = ($tpAmb == 1) ? 'producao' : 'homologacao';
        }
    }

        /**
     * Assembles all the necessary parameters for soap communication
     * @param string $service
     * @param string $uf
     * @param int $tpAmb
     * @param bool $ignoreContingency
     * @return void
     */
    protected function servico(
        $service,
        $uf,
        $tpAmb,
        $ignoreContingency = false
    ) {

        $ambiente = $tpAmb == 1 ? "producao" : "homologacao";

        $webs = new Webservices($this->getXmlUrlPath());

        // $sigla = $uf;
        $sigla = 'RS';

        $stdServ = $webs->get($sigla, $ambiente, $this->modelo);

        if ($stdServ === false) {
            throw new \RuntimeException(
                "Nenhum serviço foi localizado para esta unidade "
                . "da federação [$sigla], com o modelo [$this->modelo]."
            );
        }

        if (empty($stdServ->$service->url)) {
            throw new \RuntimeException(
                "Este serviço [$service] não está disponivel para esta "
                . "unidade da federação [$uf] ou para este modelo de Nota ["
                . $this->modelo
                ."]."
            );
        }

        //recuperação do cUF
        $this->urlcUF = $this->getcUF($uf);
        //recuperação da versão
        $this->urlVersion = $stdServ->$service->version;
        //recuperação da url do serviço
        $this->urlService = $stdServ->$service->url;
        //recuperação do método
        $this->urlMethod = $stdServ->$service->method;
        //recuperação da operação
        $this->urlOperation = $stdServ->$service->operation;

        //montagem do namespace do serviço
        $this->urlNamespace = sprintf(
            "%s/wsdl/%s",
            $this->urlPortal,
            $this->urlOperation
        );
        //montagem do cabeçalho da comunicação SOAP
        $this->urlHeader = Header::get(
            $this->urlNamespace,
            $this->urlcUF,
            $this->urlVersion
        );
        $this->urlAction = "\""
            . $this->urlNamespace
            . "/"
            . $this->urlMethod
            . "\"";


        $this->objHeader = new SoapHeader(
            $this->urlNamespace,
            'mdfeCabecMsg',
            ['cUF' => $this->urlcUF, 'versaoDados' => $this->urlVersion]
        );

    }

    /**
     * Recover path to xml data base with list of soap services
     * @return string
    */
    protected function getXmlUrlPath(){
        $file = $this->pathwsfiles
            . "wsmdfe_".$this->versao.".xml";
       
        if (! file_exists($file)) {
            return '';
        }
        return file_get_contents($file);
    }

     /**
     * Send request message to webservice
     * @param array $parameters
     * @param string $request
     * @return string
    */
    protected function sendRequest($request, array $parameters = []){
        $this->checkSoap();

        return (string) $this->soap->send(
            $this->urlService,
            $this->urlMethod,
            $this->urlAction,
            SOAP_1_2,
            $parameters,
            $this->soapnamespaces,
            $request,
            $this->objHeader
        );
    }

    /**
     * Verify if SOAP class is loaded, if not, force load SoapCurl
    */
    protected function checkSoap(){
        if (empty($this->soap)) {
            $this->soap = new SoapCurl($this->certificate);
        }
    }

    public function version($version = null){
        
        if (null === $version) {
            return $this->versao;
        }
        
        //Verify version template is defined
        if (false === isset($this->availableVersions[$version])) {
            throw new \InvalidArgumentException('Essa versão de layout não está disponível');
        }
        
        $this->versao = $version;
        $this->config->schemes = $this->availableVersions[$version];
        $this->pathschemes = realpath(
            __DIR__ . '/../../schemes/'. $this->config->schemes
        ).'/';
        
        return $this->versao;
    }

    /**
     * Recover cUF number from state acronym
     * @param string $acronym Sigla do estado
     * @return int number cUF
    */
    public function getcUF($acronym){
        return UFlist::getCodeByUF($acronym);
    }

    /**
     * Sign MDFe
     * @param  string  $xml MDFe xml content
     * @return string signed MDFe xml
     * @throws RuntimeException
    */
    
    public function signMDFe($xml){

        if (empty($xml)) {
            throw new InvalidArgumentException('$xml');
        }

        //remove all invalid strings
        $xml = Strings::clearXmlString($xml);

        $signed = Signer::sign(
            $this->certificate,
            $xml,
            'infMDFe',
            'Id',
            $this->algorithm,
            $this->canonical
        );

        $dom = new DOMDocument('1.0', 'UTF-8');

        $dom->preserveWhiteSpace = false;
        
        $dom->formatOutput = false;
        
        $dom->loadXML($signed);

        $modelo = $dom->getElementsByTagName('mod')->item(0)->nodeValue;

        $this->isValid($this->versao, $signed, 'mdfe');
        
        $domInfo = new DOMDocument('1.0', 'UTF-8');

        $domInfo->preserveWhiteSpace = false;
        
        $domInfo->formatOutput = false;

        $node = $dom->getElementsByTagName('rodo')->item(0);

        $node = $domInfo->importNode($node, true);

        $domInfo->appendChild($node);

        $this->isValid($this->versao, $domInfo->saveXML(), 'mdfeModalRodoviario');

        return $signed;
    }

    /**
     * Performs xml validation with its respective
     * XSD structure definition document
     * NOTE: if dont exists the XSD file will return true
     * @param string $version layout version
     * @param string $body
     * @param string $method
     * @return boolean
     */
    protected function isValid($version, $body, $method){
        
        $schema = $this->pathschemes.$method."_v$version.xsd";

        if (!is_file($schema)) {
            return true;
        }

        return Validator::isValid(
            $body,
            $schema
        );

    }

     /**
     * Set or get model of document MDFe = 58
     * @param int $model
     * @return int modelo class parameter
     */
    public function model($model = null)
    {
        if ($model == 58) {
            $this->modelo = $model;
        }

        return $this->modelo;
    }

}

?>