<?php

namespace Trive\Varnish\Plugin;

use Magento\Framework\HTTP\PhpEnvironment\Response as Subject;
use Magento\PageCache\Model\Config;
use Zend\Http\HeaderLoader;
use Trive\Varnish\Model\Http\XMagentoTags;

class HttpResponseSplitHeader
{
    /**
     * Approximately 8kb in length
     *
     * @var int
     */
    private $requestSize = 8000;

    /**
     * PageCache configuration
     *
     * @var Config
     */
    private $config;

    /**
     * HttpResponseSplitHeader constructor.
     *
     * @param Config   $config
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * Special case for handling X-Magento-Tags header
     * splits very long header into multiple headers
     *
     * @param Subject  $subject
     * @param \Closure $proceed
     * @param string   $name
     * @param string   $value
     * @param bool     $replace
     *
     * @return Subject|mixed
     */
    public function aroundSetHeader(Subject $subject, \Closure $proceed, $name, $value, $replace = false)
    {
        //if varnish isn't enabled, don't do anything
        if (!$this->config->isEnabled() || $this->config->getType() != Config::VARNISH
        ) {
            return $proceed($name, $value, $replace);
        }

        $this->addHeaderToStaticMap();

        if ($name == 'X-Magento-Tags') {

            $tags = (string)$value;

            $headLength = strlen($tags);

            while  ($headLength > $this->requestSize) {
                $cut = strrpos($tags, ',', $this->requestSize - $headLength);
                $subject->getHeaders()->addHeaderLine($name, substr($tags, 0, $cut ));
                $tags = substr($tags, $cut + 1);
                $headLength = strlen($tags);
            }

            $subject->getHeaders()->addHeaderLine($name, $tags);

            return $subject;
        }

        return $proceed($name, $value, $replace);
    }

    /**
     * Add X-Magento-Tags header to HeaderLoader static map
     */
    private function addHeaderToStaticMap()
    {
        HeaderLoader::addStaticMap(
            [
                'xmagentotags' => XMagentoTags::class,
            ]
        );
    }
}
