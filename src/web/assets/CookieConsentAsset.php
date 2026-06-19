<?php

namespace guimauve\cookieconsent\web\assets;

use craft\web\AssetBundle;

/**
 * Front-end asset for the Twig-rendered banner interaction logic.
 */
class CookieConsentAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@guimauve/cookieconsent/web/assets/dist';
        $this->js = ['cookie-consent.js'];

        parent::init();
    }
}
