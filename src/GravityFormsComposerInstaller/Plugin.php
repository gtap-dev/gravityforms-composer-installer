<?php

namespace gotoAndDev\GravityFormsComposerInstaller;

use Exception;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvent;
use Composer\Util\StreamContextFactory;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\EventDispatcher\EventSubscriberInterface;
use gotoAndDev\GravityFormsComposerInstaller\Exception\DownloadException;
use FFraenz\PrivateComposerInstaller\RemoteFilesystem;

class Plugin extends \FFraenz\PrivateComposerInstaller\Plugin implements PluginInterface, EventSubscriberInterface
{

    const GRAVITY_FORMS_API = 'www.gravityhelp.com';

	/**
	 * Replaces placeholders with corresponding environment variables and overrides the download URL
	 *
	 * @param  PreFileDownloadEvent  $event
	 *
	 * @return void
	 * @throws DownloadException
	 * @throws \FFraenz\PrivateComposerInstaller\Exception\MissingEnvException
	 */
	public function injectPlaceholders(PreFileDownloadEvent $event): void
	{
		$url = $event->getProcessedUrl();

		if (strpos($url, self::GRAVITY_FORMS_API) === false) {
			return;
		}

		// Check if package url contains any placeholders
		$placeholders = $this->getUrlPlaceholders($url);

		if (count($placeholders) > 0) {
			// Replace each placeholder with env var
			foreach ($placeholders as $placeholder) {
				$value = $this->env->get($placeholder);
				$url = str_replace('{%' . $placeholder . '}', $value, $url);
			}

			// Override the Gravity Forms' URL with the AWS URL retrieved from the API
			$url = $this->getDownloadUrl($url);

			// Download file from different location
			$originalRemoteFilesystem = $event->getRemoteFilesystem();

			$event->setRemoteFilesystem(new RemoteFilesystem(
				$url,
				$this->io,
				$this->composer->getConfig(),
				$originalRemoteFilesystem->getOptions(),
				$originalRemoteFilesystem->isTlsDisabled()
			));

			// Stop other events from running and overriding this
			$event->stopPropagation();
		}
	}

	/**
	 * Get the AWS Download URL from the Gravity Forms API
	 *
	 * @param $url
	 *
	 * @return string
	 * @throws DownloadException
	 * @throws Exception
	 */
    protected function getDownloadUrl($url)
    {
	    $result = file_get_contents($url, false, $this->getHttpContext($url));

	    if(false === $result) {
		    throw new DownloadException($url);
	    }

        $body        = trim($result);
        $plugin_info = unserialize($body);

        $download_url = isset($plugin_info['download_url_latest']) ? $plugin_info['download_url_latest'] : '';

        if(empty($download_url)) {
        	throw new Exception( 'Unable to find download URL. Check your Gravity Forms API key.' );
        }

        return $download_url;
    }

	/**
	 * Get the HTTP context for this URL
	 *
	 * @param $url
	 *
	 * @return resource
	 */
    protected function getHttpContext($url)
    {
        return StreamContextFactory::getContext($url);
    }
}
