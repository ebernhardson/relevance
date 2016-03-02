<?php

namespace WikiMedia\RelevanceScoring\Import;

class ImportedResult {
    private $source;
    private $title;
    private $snippet;

    public function __construct($source, $title, $snippet, $position) {
        $this->source   = $source;
        $this->title    = $title;
        $this->snippet  = $snippet;
        $this->position = $position;
    }

    static public function createFromURL($source, $url, $snippet, $position) {
        $path = parse_url($url, PHP_URL_PATH);
         // make the bold assumption wikimedia wikis all
        // prefix with /wiki/
        $prefix = '/wiki/';
        if (false === substr($path, 0, strlen($prefix))) {
            throw new \Exception("Invalid url: $url");
        }
        $title = strtr(substr($path, strlen($prefix)), '_', ' ');

        return new self($source, $title, $snippet, $position);
    }

    public function getSource() {
        return $this->source;
    }

    public function getTitle() {
        return $this->title;
    }

    public function getSnippet() {
        return $this->snippet;
    }

    public function getPosition() {
        return $this->position;
    }
}
