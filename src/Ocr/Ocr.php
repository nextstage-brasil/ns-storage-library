<?php

namespace NsStorageLibrary\Ocr;

use NsStorageLibrary\Ocr\Adapter\OcrGCP;
use NsStorageLibrary\Ocr\Adapter\OcrLocal;
use NsStorageLibrary\Ocr\Adapter\OcrS3;

class Ocr {

    public static function getAdapter($adapterName = false) {
        if (!$adapterName) {
            $adapterName = \NsStorageLibrary\Config::init()['StoragePrivate'];
        }
        switch ($adapterName) {
            case 'S3':
                return new OcrS3();
                break;
            case 'GCP':
                return new OcrGCP();
                break;
            case 'Local':
                return new OcrLocal();
                break;
            default:
                die('OCR não definido: ' . $adapterName);
                break;
        }
    }

}
