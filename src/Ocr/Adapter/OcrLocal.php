<?php

namespace NsStorageLibrary\Ocr\Adapter;

use Exception;
use League\Flysystem\Config as Config2;
use NsStorageLibrary\Config;
use Smalot\PdfParser\Parser;

class OcrLocal implements OcrInterface {

    private $content, $type, $valid, $filename;

    public function __construct() {
        $init = Config::init();
        $this->cfg = new Config2($init);
        $this->out = $this->cfg->get('path_tmp') . '/tmp';
        \NsStorageLibrary\Util::mkdir($this->out);
    }

    public function create_request($path, $wait = false) {
        $chave = md5($path);
        if ($this->has_json($path)) {
            return file_get_contents($this->out . '/' . $chave);
        }

        switch (true) {
            // testar o path puro enviado
            case (file_exists($path)): // testar o arquivo enviado
                $this->filename = $path;
                break;
            // testar no diretoiro de uploadfile
            case (file_exists($this->cfg->get('path_uploadfile') . DIRECTORY_SEPARATOR . $path)):
                $this->filename = $this->cfg->get('path_uploadfile') . DIRECTORY_SEPARATOR . $path;
                break;
            // testar no storage Local
            case (file_exists($this->cfg->get('local')['diretorio_local'] . DIRECTORY_SEPARATOR . $this->cfg->get('pathPrefix') . DIRECTORY_SEPARATOR . $path)):
                $this->filename = $this->cfg->get('local')['diretorio_local'] . DIRECTORY_SEPARATOR . $this->cfg->get('pathPrefix') . DIRECTORY_SEPARATOR . $path;
                break;
            default:
                throw new Exception(__METHOD__ . ' - File not exists: ' . $path);
        }

        $this->content = file_get_contents($this->filename);
        $this->setType();
        $ret = $this->parse();
        if ($ret) {
            file_put_contents($this->out . '/' . $chave, $ret);
        }
        return $ret;
    }

    public function detect_document_text_gcs($path) {
        return $this->create_request($path);
    }

    public function detect_pdf_gcs($path) {
        return $this->create_request($path);
    }

    public function has_json($path) {
        $chave = md5($path);
        return file_exists($this->out . '/' . $chave);
    }

    public function parse() {
        $this->setType();
        //$ret['type'] = $this->type;
        $ret = __CLASS__ . ' empty';


        switch (true) {
            case (stripos($this->type, 'image') > -1):
                $ret = __CLASS__ . ' - Tesseract não está disponível';
                /*
                  // preciso salvar o conteudo em disco para tesseract
                  $image = str_replace('src', '.tmp', __DIR__) . '/' . md5($this->filename);
                  file_put_contents($image, $this->content);
                  sleep(0.1); // concorrencia de disco
                  $ocr = (new TesseractOCR($image))
                  ->run();
                  $ret['content'] = str_replace(["'", '"', "\n"], '', $ocr);
                  unlink($image);
                 * 
                 */
                break;
            case (stripos($this->type, 'pdf') > -1):
                //unset($this->content);
                $size = filesize($this->filename);
                if ($size > $this->cfg->get('Local')['limiteSizeToOcr']) {
                    throw new Exception(__METHOD__ . "Arquivo '$this->filename' é maior que o configurado. $size > " . $this->cfg->get('Local')['limiteSizeToOcr']);
                }

                $parser = new Parser();
                $pdf = $parser->parseFile($this->filename);

                // Retrieve all pages from the pdf file.
                $pages = $pdf->getPages();
                unset($pdf);
                // Loop over each page to extract text.
                $ret = '';
                foreach ($pages as $page) {
                    $ret .= $page->getText();
                }

                //$ret = str_replace(["'", '"', "\n"], '', $text);
                break;
            default:
                http_response_code(401);
                throw new Exception(__CLASS__ . ' - Type "' . $this->type . '" is not defined. Path: ' . $this->filename);
                break;
        }
        return "[nsOcrLOCAL]" . \NsStorageLibrary\Util::convertAscii($ret);
    }

    /**
     * @param string $str Content do arquivo
     * @return type
     */
    private function setType() {
        $f = finfo_open();
        $mime_type = finfo_buffer($f, $this->content, FILEINFO_MIME_TYPE);
        $this->type = $mime_type;
        if (stripos($this->type, 'image') > -1 || stripos($this->type, 'pdf') > -1) {
            $this->valid = true;
        }
    }

}
