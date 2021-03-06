<?php

/*
 * This File is part of the Lucid\Module\Routing\Cache package
 *
 * (c) iwyg <mail@thomas-appel.com>
 *
 * For full copyright and license information, please refer to the LICENSE file
 * that was distributed with this package.
 */

namespace Lucid\Module\Routing\Cache;

use Lucid\Module\Resource\Loader\LoaderInterface;

/**
 * @class RouterCache
 *
 * @package Selene\Package\Framework\Routing
 * @version $Id$
 * @author iwyg <mail@thomas-appel.com>
 */
class RouterCache implements LoaderListener
{
    /**
     * storage
     *
     * @var StorageInterface
     */
    private $storage;

    /**
     * resources
     *
     * @var CollectorInterface
     */
    private $resources;

    /**
     * loader
     *
     * @var LoaderInterface
     */
    private $loader;

    /**
     * current
     *
     * @var string
     */
    private $current;

    /**
     * debug
     *
     * @var boolean
     */
    private $debug;

    /**
     * meta
     *
     * @var array
     */
    private $meta;

    /**
     * metaCache
     *
     * @var array
     */
    private $metaCache;

    /**
     * Constructor
     *
     * @param string $resource
     * @param Store $storage
     * @param Loader $loader
     * @param boolean $debug
     */
    public function __construct($res, $manifest, RouteCacheInterface $cache, LoaderInterface $loader, $debug = false)
    {
        $this->debug    = $debug;
        $this->loader   = $loader;
        $this->storage  = $cache;
        $this->manifest = $manifest;

        $this->setResources($res);

        $this->loader->addListener($this);
        $this->meta = [];
        $this->metaCache = [];
    }

    /**
     * Checks if the cache is valid.
     *
     * @return boolean
     */
    public function isValid()
    {
        if ($this->storage->exists() && $this->resources->isValid($this->storage->getLastWriteTime())) {
            return $this->isDebugging() ? $this->validateManifest() : true;
        }

        return false;
    }

    /**
     * Loads the routes into a collection.
     *
     * Loads the resources from cache if the cache is still valid, or re-loads
     * and re-creates the cache if neccessary.
     *
     * @return RouteCollectionInterface
     */
    public function load()
    {
        if (!$this->isValid()) {
            $this->doLoadResources();
        }

        return $this->storage->read();
    }

    /**
     * Loading callback.
     *
     * Collects files that've been included during the loading process of the
     * main resource files.
     *
     * @param string $resource
     *
     * @return void
     */
    public function onLoaded($resource)
    {
        if (!$this->isDebugging() || $this->current === $resource) {
            return;
        }

        $this->ensureCollector($this->current);
        $this->meta[$this->current]->addFileResource($resource);
    }

    /**
     * Check if in debug mode.
     *
     * @return boolean
     */
    protected function isDebugging()
    {
        return $this->debug;
    }

    /**
     * Set the main resource files.
     *
     * @param string|array|CollectorInterface $resources
     *
     * @return void
     */
    protected function setResources($resources)
    {
        if (!$resources instanceof CollectorInterface) {
            $files = (array)$resources;
            $resources = new Collector;

            foreach ($files as $file) {
                $resources->addFileResource($file);
            }
        }

        $this->resources = $resources;
    }

    /**
     * Loads the main resources and caches the results.
     *
     * If debuggin, all included resources will be put into a manifest file to
     * keep track of their changes.
     *
     * @return void
     */
    protected function doLoadResources()
    {
        $collection = new RouteCollection;

        foreach ($this->resources->getResources() as $i => $resource) {
            // dont't add the prmary resource to
            $collection->merge($this->loader->load($this->current = (string)$resource));
        }

        if ($this->isDebugging()) {
            $this->writeManifests();
        }

        $this->current = null;
        $this->storage->write($collection);
    }

    /**
     * writeManifests
     *
     * @return void
     */
    protected function writeManifests()
    {
        foreach ($this->resources->getResources() as $resource) {
            $manifest = $this->getManifestFileName($file = (string)$resource);
            $this->ensureCollector($file);

            $this->writeManifest($manifest, $this->meta[$file]);
        }
    }

    /**
     * writeManifest
     *
     * @param string $file
     * @param CollectorInterface $resources
     * @throws \RuntimeException if creation of cache dir fails.
     *
     * @return void
     */
    protected function writeManifest($file, CollectorInterface $resources)
    {
        $mask = 0755 & ~umask();

        if (!is_dir($dir = dirname($file))) {
            if (false === @mkdir($dir, $mask, true)) {
                throw new \RuntimeException('Creating manifest for router cache failed.');
            }
        } elseif (false === @chmod($dir, $mask)) {
            throw new \RuntimeException('Cannot apply permissions on cache directory.');
        }

        file_put_contents($file, serialize($resources), LOCK_EX);
    }

    /**
     * ensureCollector
     *
     * @param mixed $file
     *
     * @return void
     */
    protected function ensureCollector($file, $manifest = null)
    {
        if (!isset($this->meta[$file])) {

            if (null !== $manifest && file_exists($manifest)) {
                $this->meta[$file] = unserialize(file_get_contents($manifest));
            } else {
                $this->meta[$file] = new Collector;
            }
        }
    }

    /**
     * validateManifest
     *
     * @return void
     */
    protected function validateManifest()
    {
        foreach ($this->resources->getResources() as $resource) {

            $file = $this->getManifestFileName((string)$resource);

            if (!file_exists($file)) {
                return false;
            }

            $time = filemtime($file);
            $manifest = unserialize(file_get_contents($file));

            if (!$manifest->isValid($time)) {
                return false;
            }
        }

        return true;
    }

    /**
     * getManifestFileName
     *
     * @param string $file
     *
     * @return string
     */
    protected function getManifestFileName($file)
    {
        if (!isset($this->metaCache[$file])) {

            $ds = DIRECTORY_SEPARATOR;

            $name = substr_replace(
                substr_replace(
                    md5_file($file) . '_'.basename($file) . '.manifest',
                    $ds,
                    4,
                    0
                ),
                $ds,
                2,
                0
            );

            $this->metaCache[$file] = sprintf('%s%s%s', $this->manifest, $ds, $name);
        }

        return $this->metaCache[$file];
    }

    /**
     * getManifest
     *
     * @return void
     */
    protected function getManifest()
    {
        return $this->manifest;
    }
}
