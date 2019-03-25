<?php

namespace gotoAndDev\GravityFormsComposerInstaller\Exception;

class DownloadException extends \Exception
{
    public function __construct($key)
    {
        parent::__construct(sprintf(
            'Can\'t get correct download URL for \'%1$s\'. ',
            $key
        ));
    }
}
