<?php

namespace NekoAPI\Tool\ProjectGenerator\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use NekoAPI\Tool\ProjectGenerator\Console\Command\GenerateCommand;

/**
 * Class Application
 *
 * @package NekoAPI\Tool\ProjectGenerator
 * @author  Aurimas Niekis <aurimas@niekis.lt>
 */
class Application extends BaseApplication
{
    /**
     * @inheritDoc
     */
    public function __construct()
    {
        error_reporting(-1);

        parent::__construct('NekoAPI Project Generator', '1.0');
    }

    /**
     * @inheritDoc
     */
    public function getLongVersion()
    {
        $version = parent::getLongVersion() .
            ' by <comment>Aurimas Niekis</comment>';

        return $version;
    }

    /**
     * @inheritDoc
     */
    public function getDefinition()
    {
        $inputDefinition = parent::getDefinition();
        $inputDefinition->setArguments();

        return $inputDefinition;
    }

    /**
     * @inheritDoc
     */
    protected function getCommandName(InputInterface $input)
    {
        return 'main';
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultCommands()
    {
        $defaultCommands = parent::getDefaultCommands();

        $defaultCommands[] = new GenerateCommand();

        return $defaultCommands;
    }

}
