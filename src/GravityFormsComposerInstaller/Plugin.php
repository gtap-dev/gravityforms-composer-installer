<?php

namespace gotoAndDev\GravityFormsComposerInstaller;

use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Plugin\PluginInterface;
use Composer\Util\StreamContextFactory;
use Exception;
use gotoAndDev\GravityFormsComposerInstaller\Exception\DownloadException;

class Plugin extends \FFraenz\PrivateComposerInstaller\Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * Gravity Forms Plugin API endpoint.
     */
    const GRAVITY_FORMS_API = 'www.gravityhelp.com';

    /**
     * Get the AWS Download URL from the Gravity Forms API.
     *
     * @param $url
     *
     * @return string The replaced gravity forms API URL.
     * @throws DownloadException
     * @throws Exception
     */
    public function fulfillPlaceholders(?string $url): ?string
    {
        $url = parent::fulfillPlaceholders($url);

        if (strpos($url, self::GRAVITY_FORMS_API) === false) {
            throw new Exception('URL does not match the Gravity Forms API endpoint.');
        }

        $result = file_get_contents($url, false, StreamContextFactory::getContext($url));

        if (false === $result) {
            throw new DownloadException($url);
        }

        $body = trim($result);
        $plugin_info = unserialize($body);

        $download_url = isset($plugin_info['download_url_latest']) ? $plugin_info['download_url_latest'] : '';

        if (empty($download_url)) {
            throw new Exception('Unable to find download URL. Check your Gravity Forms API key.');
        }

        return (string) $download_url;
    }
}
