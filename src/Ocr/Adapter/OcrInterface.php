<?php

namespace NsStorageLibrary\Ocr\Adapter;

interface OcrInterface {

    public function has_json($path);

    public function create_request($path, $wait = false);

    public function detect_pdf_gcs($path);

    public function detect_document_text_gcs($path);
}
