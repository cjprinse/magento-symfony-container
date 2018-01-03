<?php

use ContainerTools\Configuration;
use ContainerTools\ContainerGenerator;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Container;
use Bridge\MageApp;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

class Inviqa_SymfonyContainer_Helper_ContainerProvider
{
    const HELPER_NAME = 'inviqa_symfonyContainer/containerProvider';

    /**
     * @var Mage_Core_Model_App
     */
    private $_mageApp;

    /**
     * @var Container
     */
    private $_container;

    /**
     * @var Configuration
     */
    private $_generatorConfig;

    /**
     * @var CompilerPassInterface
     */
    private $_storeConfigCompilerPass;
    /**
     * @var CompilerPassInterface
     */
    private $_injectableCompilerPass;

    public function __construct(array $services = array())
    {
        $this->_mageApp = isset($services['app']) ? $services['app'] : Mage::app();

        $this->_generatorConfig = isset($services['generatorConfig']) ?
            $services['generatorConfig'] :
            Mage::getModel('inviqa_symfonyContainer/configurationBuilder')->build();

        $this->_storeConfigCompilerPass = isset($services['storeConfigCompilerPass']) ?
            $services['storeConfigCompilerPass'] :
            Mage::getModel('inviqa_symfonyContainer/storeConfigCompilerPass');

        $this->_injectableCompilerPass = isset($services['injectableCompilerPass']) ?
            $services['injectableCompilerPass'] :
            Mage::getModel('inviqa_symfonyContainer/injectableCompilerPass');
    }

    /**
     * @return Container
     */
    public function getContainer()
    {
        return $this->_container ?: $this->_buildContainer();
    }

    /**
     * @return Container
     */
    private function _buildContainer()
    {
        if (!$this->_container instanceof ProjectServiceContainer) {
            $cacheFile = $this->_generatorConfig->getContainerFilePath();
            $containerConfigCache = new ConfigCache($cacheFile, $this->_generatorConfig->getDebug());

            if (!$containerConfigCache->isFresh()) {
                $this->_generatorConfig->addCompilerPass($this->_storeConfigCompilerPass);
                $this->_generatorConfig->addCompilerPass($this->_injectableCompilerPass);

                $this->_mageApp->dispatchEvent(
                    'symfony_container_before_container_generator',
                    ['generator_config' => $this->_generatorConfig]
                );

                $generator = new ContainerGenerator($this->_generatorConfig);
                $containerBuilder = $generator->getContainer();

                $dumper = new PhpDumper($containerBuilder);
                $containerConfigCache->write(
                    $dumper->dump(),
                    $containerBuilder->getResources()
                );
            }

            require_once $cacheFile;
            $this->_container = new ProjectServiceContainer();
        }

        return $this->_container;
    }
}
