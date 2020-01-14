<?php

namespace gotoAndDev\GravityFormsComposerInstaller;

use Composer\Composer;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Util\StreamContextFactory;
use Dotenv\Dotenv;
use Exception;
use gotoAndDev\GravityFormsComposerInstaller\Exception\MissingEnvException;
use gotoAndDev\GravityFormsComposerInstaller\Exception\DownloadException;

class Plugin implements PluginInterface, EventSubscriberInterface
{

    const GRAVITY_FORMS_API = 'www.gravityhelp.com';

    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    /**
     * @var boolean
     */
    protected $envInitialized = false;

    /**
     * Run PRE_FILE_DOWNLOAD before ffraenz/private-composer-installer and hirak/prestissimo
     */
    public static function getSubscribedEvents()
    {
	    return [
		    PluginEvents::PRE_FILE_DOWNLOAD    => [ 'injectPlaceholders', -2 ],
	    ];
    }

    /**
     * @inheritdoc
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

	/**
	 * Replaces placeholders with corresponding environment variables and replaces the download URL.
	 *
	 * @param  PreFileDownloadEvent  $event
	 *
	 * @return void
	 * @throws DownloadException
	 * @throws MissingEnvException
	 */
    public function injectPlaceholders(PreFileDownloadEvent $event)
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
                $value = $this->getEnv($placeholder);
                $url   = str_replace('{%'.$placeholder.'}', $value, $url);
            }

            // Replace URL with Gravity Form's AWS url
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
    public function getDownloadUrl($url)
    {
	    $result = file_get_contents($url, false, $this->getHttpContext($url));

	    if( false === $result ) {
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
     * Returns package for given operation.
     *
     * @param OperationInterface $operation
     * @return PackageInterface
     */
    protected function getOperationPackage(OperationInterface $operation)
    {
        if ($operation->getJobType() === 'update') {
            return $operation->getTargetPackage();
        }
        return $operation->getPackage();
    }

	/**
	 * Retrieves environment variable for given key.
	 *
	 * @param  string  $key
	 *
	 * @return mixed
	 * @throws MissingEnvException
	 */
    protected function getEnv($key)
    {
        // Retrieve env var
        $value = getenv($key);

        // Lazily initialize environment if env var is not set
        if (empty($value) && ! $this->envInitialized) {
            $this->envInitialized = true;

            // Load dot env file if it exists
            if (file_exists(getcwd() . DIRECTORY_SEPARATOR . '.env')) {
                $dotenv = Dotenv::create(getcwd());
                $dotenv->load();

                // Retrieve env var from dot env file
                $value = getenv($key);
            }
        }

        // Check if env var is set
        if (empty($value)) {
            throw new MissingEnvException($key);
        }

        return $value;
    }

    /**
     * Retrieves placeholders for given url.
     *
     * @param string $url
     * @return string[]
     */
    protected function getUrlPlaceholders($url)
    {
        $matches = [];
        preg_match_all('/{%([A-Za-z0-9-_]+)}/', $url, $matches);

        $placeholders = [];
        foreach ($matches[1] as $match) {
            array_push($placeholders, $match);
        }
        return array_unique($placeholders);
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
