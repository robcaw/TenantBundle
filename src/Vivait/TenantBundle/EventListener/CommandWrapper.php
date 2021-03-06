<?php

namespace Vivait\TenantBundle\EventListener;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Vivait\TenantBundle\Registry\TenantRegistry;

class CommandWrapper {
    /**
     * @var TenantRegistry
     */
    private $tenantRegistry;

    static $wrapped = false;

    function __construct( TenantRegistry $tenantRegistry ) {
        $this->tenantRegistry = $tenantRegistry;
    }

    public function onConsoleCommand(ConsoleCommandEvent $event) {
        $command = $event->getCommand();
        $application = $command->getApplication();
        $inputDefinition = $application->getDefinition();

        // It's not a Symfony command, no need to tenant it
        if (!$application instanceOf Application) {
            return;
        }

        $kernel = $application->getKernel();

        $output = $event->getOutput();
        $input = $event->getInput();
        $originalInput = clone $input;

        $this->alterInputDefinition( $inputDefinition, $input, $command );

        $tenant = $input->getOption('tenant');

        if ($tenant !== null && !self::$wrapped) {
            self::$wrapped = true;

            foreach ($this->tenantRegistry->getAll() as $id => $tenant) {
                $this->performCommand( $kernel, $id, $originalInput, $output );
            }

            self::$wrapped = false;
        }
    }

    /**
     * Gets tenantRegistry
     * @return TenantRegistry
     */
    public function getTenantRegistry() {
        return $this->tenantRegistry;
    }

    /**
     * Sets tenantRegistry
     * @param TenantRegistry $tenantRegistry
     * @return $this
     */
    public function setTenantRegistry( $tenantRegistry ) {
        $this->tenantRegistry = $tenantRegistry;

        return $this;
    }

    /**
     * @param KernelInterface $kernel
     * @param integer $environment
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    private function performCommand( KernelInterface $kernel, $environment, InputInterface $input, OutputInterface $output ) {
        $kernelClass = get_class( $kernel );
        $clonedKernel = new $kernelClass( 'tenant_'. $environment, $kernel->isDebug() );
        $application = new Application( $clonedKernel );

        $application->doRun( $input, $output );
    }

    /**
     * @param InputDefinition $inputDefinition
     * @param InputInterface $input
     * @param Command $command
     * @return ArgvInput
     */
    private function alterInputDefinition( InputDefinition $inputDefinition, InputInterface $input, Command $command ) {
        $inputDefinition->addOption(
            new InputOption( 'tenant', null, InputOption::VALUE_OPTIONAL, 'The tenant which you wish to perform the command on, defaults to all tenants', null )
        );

        // merge the application's input definition
        $command->mergeApplicationDefinition();

        // we use the input definition of the command
        $input->bind( $command->getDefinition() );

        return $input;
    }
}
