<?php

namespace NsStorageLibrary\Storage\Adapter;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;
use League\Flysystem\Util\MimeType;

//use function GuzzleHttp\json_decode;

class FilerunAdapter extends AbstractAdapter {

    private $url;
    private $redirect_uri = 'http://localhost';
    private $client_id;
    private $client_secret;
    private $username;
    private $password;
    private $error;
    private $scope; //OAuth2 scope
    private $access_token; //the OAuth2 access token
    private $http; //the Guzzle HTTP Client
    public $debug = false;
    static $path = '/ROOT/HOME';
    private $pathInit, $path_write;

    public function __construct(array $options = []) {
        $this->setBucket($options['bucket']);
        $this->setPathPrefix($options['pathPrefix']);
        $prefix = $options['pathPrefix'];
        unset($options['pathPrefix']);

        foreach ($options as $option => $value) {
            if (property_exists($this, $option)) {
                $this->{$option} = $value;
            }
        }
        $this->http = new HttpClient();
        $hoje = (int) date('Ym');

        $file = $this->path_write . '/filerun_' . $options['getLinkPublic'] . '.php';


        $lastCreated = 0;
        if (file_exists($file)) {
            $lastCreated = file_get_contents($file); // controle para criar as pastas padrões apenas uma vez
        }
        // criação da estrutura de pastas, pois o filesystem nao cria automatico
        if ($this->connect() && $hoje > $lastCreated) {


            // criação do diretorio inicial - mes
            $path = date('Y') . '/' . date('m') . '/created.ini';
            if (!$this->has($path)) { // caso arquivo não exitsa, ira criar a arvore de diretorios e salvar o arquivo para verificação futura
                $this->createDir($path, new Config()); // ira criar a arvore de diretorios, caso precise
                $this->write($path, 'Created at ' . date('Y-m-d H:i:s'), new Config());

                // criar no diretorio public tbem
                $this->setBucket($options['bucket-public']);
                $this->createDir($path, new Config()); // ira criar a arvore de diretorios, caso precise
                $this->write($path, 'Created at ' . date('Y-m-d H:i:s'), new Config());
                $this->setBucket($options['bucket']);
            }

            /*
              // criação do diretorio inicial - public (thumbs
              $path = 'public/' . date('Y') . '/' . date('m') . '/created.ini';
              if (!$this->has($path)) { // caso arquivo não exitsa, ira criar a arvore de diretorios e salvar o arquivo para verificação futura
              $this->createDir($path); // ira criar a arvore de diretorios, caso precise
              $this->write($path, 'Created at ' . date('Y-m-d H:i:s'));
              }

              // criação da pasta trash
              $path = 'trash/created.ini';
              if (!$this->has($path)) { // caso arquivo não exitsa, ira criar a arvore de diretorios e salvar o arquivo para verificação futura
              $this->createDir($path); // ira criar a arvore de diretorios, caso precise
              $this->write($path, 'Created at ' . date('Y-m-d H:i:s'));
              }
             * 
             */

            //file_put_contents($file, $hoje);
        }
    }

    public function connect() {
        try {
            $response = $this->http->request('POST', $this->url . '/oauth2/token/', [
                'form_params' =>
                    [
                    'client_id' => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'username' => $this->username,
                    'password' => $this->password,
                    'redirect_uri' => $this->redirect_uri,
                    'grant_type' => 'password',
                    'scope' => implode(' ', $this->scope)
                ],
                'verify' => false
            ]);
        } catch (RequestException $e) {
            self::logthis(__METHOD__ . __LINE__, $e->getMessage());
            throw new \Exception('Erro ao conectar Filerun: ' . $e->getMessage());
            return false;
        }
        if (!$response) {
            $this->error = 'Unexpected empty server response';
            self::logthis(__METHOD__ . __LINE__, $this->error);
            return false;
        }
        $responseBody = $response->getBody()->getContents();
        $rs = json_decode($responseBody);
        if (!is_object($rs)) {
            $this->error = 'Unexpected server response: ' . $responseBody;
            self::logthis(__METHOD__ . __LINE__, $this->error);
            return false;
        }
        if (isset($rs->error)) {
            $this->error = 'Server error: ' . $rs->message;
            self::logthis(__METHOD__ . __LINE__, $this->error);
            return false;
        }
        if (!$rs->access_token) {
            $this->error = 'Missing access token';
            self::logthis(__METHOD__ . __LINE__, $this->error);
            return false;
        }
        $this->access_token = $rs->access_token;
        return true;
    }

    public function getAccessToken() {
        return $this->access_token;
    }

    public function setAccessToken($token) {
        $this->access_token = $token;
        return true;
    }

    static function logthis($name, $log) {
        // futuramente, logar se preciso
        /*
          $replace = str_replace('/', DIRECTORY_SEPARATOR, 'library/_lib/storage/src/Adapter/');
          $dir = str_replace($replace, '', __DIR__);
          // \Log::log('filerun', $name . ': ' . json_encode($log));

          if (defined('FROMCRON')) {
          //echo "$name : $log" . PHP_EOL;
          }
         */


        //file_put_contents('', date('Y-m-d H:i:s') . ' ' . $name . ': ' . json_encode($log) . "\n", FILE_APPEND);
    }

    public function callAPI($path, $method = 'GET', $opts = [], $raw = false) {
        self::logthis('APICall', $opts);
        try {
            $p = [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->access_token
                ],
                'verify' => false
            ];
            if (sizeof($opts) > 0) {
                $p = array_merge($p, $opts);
            }
            $response = $this->http->request($method, $this->url . '/api.php' . $path, $p);
        } catch (RequestException $e) {
            echo $e->getMessage();
            $this->error = $e->getResponse()->getBody()->getContents();
            self::logthis(__METHOD__ . __LINE__, $this->error);
            return false;
        }
        if (!$response) {
            $this->error = 'Empty server response';
            self::logthis(__METHOD__ . __LINE__, $this->error);
            return false;
        }
        $contents = $response->getBody()->getContents();
        if ($raw) {
            return $contents;
        }
        $decoded = json_decode($contents, true);
        if (is_null($decoded)) {
            if ($this->debug) {
                echo $contents;
            }
            $this->error = 'Failed to decode JSON server response: ' . json_last_error_msg();
            ////file_put_contents(__DIR__ . '/Filerun.log', date('Y-m-d H:i:s') . 'EU TESTANDO: ' . $this->error . '|'.$response->getBody()->getContents(). "\n", FILE_APPEND);
            self::logthis(__METHOD__ . __LINE__, $this->error);
            return false;
        }
        if (array_key_exists('success', $decoded) && !$decoded['success']) {
            $this->error = $decoded['error'];
        }
        //self::logthis(__METHOD__ . $path, $decoded['success']);
        return $decoded;
    }

    public function getError() {
        return $this->error;
    }

    public function copy($path, $newpath) {
        
    }

    /**
     * 28/11/2018 - Testado e funcionando
     * @param type $dirname
     * @param type $config
     * @return boolean
     */
    public function createDir($dirname, Config $config) {
        $dirname = $this->applyPathPrefix($dirname);
        $parts = explode('/', str_replace('\\', '/', $dirname));
        if (stripos($dirname, '.') > -1) {
            array_pop($parts); //extrai elemento do final do array, caso venha com nome do arquivo
        }
        $path = $this->pathInit;
        foreach ($parts as $part) {
            $opts['form_params'] = ['path' => $path, 'name' => $part];
            $path .= "$part/";
            $this->callAPI('/files/createfolder/', 'POST', $opts);
        }
        return true;
    }

    public function delete($path) {
        $path = $this->pathInit . $this->applyPathPrefix($path);
        self::logthis(__METHOD__, $path);
        $opts = ['form_params' => ['path' => $path, 'permanent' => false]];
        return $this->callAPI('/files/delete/', 'POST', $opts);
    }

    public function move($path, $moveTo) {
        $path = $this->pathInit . $this->applyPathPrefix($path);
        $newpath = $this->pathInit . $this->applyPathPrefix($moveTo);
        $opts = ['form_params' => ['path' => $path, 'moveTo' => $newpath]];
        return $this->callAPI('/files/move/', 'POST', $opts);
    }

    public function deleteDir($dirname) {
        
    }

    public function getMetadata($path) {
        $opts = ['form_params' => $params];
        return $this->callAPI('/files/metadata/', 'POST', $opts);
    }

    public function getMimetype($path) {
        $metadata['mimetype'] = MimeType::detectByFilename($path);
    }

    public function getSize($path) {
        
    }

    public function getTimestamp($path) {
        
    }

    public function getVisibility($path) {
        
    }

    public function has($path) {
        $path = $this->pathInit . $this->applyPathPrefix($path);
        self::logthis(__METHOD__, $path);
        $t = explode('/', $path);
        $file = $t[count($t) - 1];
        array_pop($t);

        $opts = ['form_params' => ['path' => implode('/', $t), 'filename' => $file]];


        $ret = $this->callAPI('/files/search/', 'POST', $opts);
        self::logthis(__METHOD__, $ret);
        return (boolean) $ret['data']['count'];
    }

    public function listContents($directory = '', $recursive = false) {
        
    }

    public function read($path) {
        $path = $this->pathInit . $this->applyPathPrefix($path);
        self::logthis(__METHOD__, $path);
        $opts = ['form_params' => ['path' => $path]];
        $content = $this->callAPI('/files/download/', 'POST', $opts, true);
        self::logthis(__METHOD__, $content);
    }

    public function readStream($path) {
        return $this->read($path);
    }

    public function rename($path, $newpath) {
        $path = $this->pathInit . $this->applyPathPrefix($path);
        $newpath = $this->pathInit . $this->applyPathPrefix($newpath);
        $opts = ['form_params' => ['path' => $path, 'newName' => $newpath]];
        return $this->callAPI('/files/rename/', 'POST', $opts);
    }

    public function setVisibility($path, $visibility) {
        
    }

    public function update($path, $contents, Config $config) {
        return $this->write($path, $contents, $config);
    }

    public function updateStream($path, $resource, Config $config) {
        return $this->write($path, $contents, $config);
    }

    /**
     * 28/11/2018 testado
     * @param type $path
     * @param type $contents
     * @param $config 
     * @return type
     */
    public function write($path, $contents, Config $config) {

        // validação pra saber se o diretório em questão existe
        $t = explode('/', $path);
        unset($t[count($t) - 1]);
        $createdIni = implode('/', $t);
        //file_put_contents(constant('PATH') . '/app/FR_createdDir.log', "\n--Write: $path", FILE_APPEND);
        if (!in_array($createdIni, $_SESSION['FR_hasDir' . $this->getBucket()])) { // só vai entrara uma vez na sessão
            $this->logthis('write: validar se diretorio existe', $path);
            $_SESSION['FR_hasDir' . $this->getBucket()][] = $createdIni;
            //file_put_contents($this->path_write . '/FR_createdDir.log', "\nVer se existe dir: $createdIni", FILE_APPEND);
            //if (!$this->has($createdIni)) { // caso arquivo não exitsa, ira criar a arvore de diretorios e salvar o arquivo para verificação futura
            $this->createDir($createdIni, new Config()); // ira criar a arvore de diretorios, caso precise
            //$this->write($createdIni, 'Created at ' . date('Y-m-d H:i:s'), new Config());
            //file_put_contents(constant('PATH') . '/app/FR_createdDir.log', "\nCriado dir: $createdIni", FILE_APPEND);
            //}
        }


        $location = $this->applyPathPrefix($path);


        self::logthis(__METHOD__, $location);
        $params = [
            'path' => $this->pathInit . $location
        ];
        $opts = [
            'multipart' => [
                    [
                    'name' => 'file',
                    'filename' => 'filename.txt',
                    'contents' => $contents
                ]
            ]
        ];
        foreach ($params as $k => $v) {
            $opts['multipart'][] = ['name' => $k, 'contents' => $v];
        }
        return $this->callAPI('/files/upload/', 'POST', $opts);
    }

    public function writeStream($path, $resource, Config $config) {
        return $this->write($path, $resource, new Config());
    }

    // Definição da pasta inicial
    public function setBucket($bucket) {
        $this->pathInit = self::$path . (($bucket) ? '/' . $bucket . '/' : '/');
    }

    public function getBucket() {
        return $this->pathInit;
    }

}
