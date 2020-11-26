<?php

namespace Codeception\Extension;

use Codeception\Events;
use Codeception\Exception\ExtensionException;
use Codeception\Extension;

class JiraExtension extends \Codeception\Extension
{
    // list events to listen to
    // Codeception\Events constants used to set the event

    public static $events = array(
        Events::SUITE_AFTER  => 'afterSuite',
        Events::TEST_BEFORE => 'beforeTest',
        Events::STEP_BEFORE => 'beforeStep',
        Events::TEST_FAIL => 'testFailed',
        Events::RESULT_PRINT_AFTER => 'print',
    );

    // methods that handle events

    public function afterSuite(\Codeception\Event\SuiteEvent $e) {
        echo('### THIS IS THE AFTER SUITE EVENT ###');
    }

    public function beforeTest(\Codeception\Event\TestEvent $e) {
        echo('@@@ THIS IS THE BEFORE TEST EVENT @@@');
    }

    public function beforeStep(\Codeception\Event\StepEvent $e) {
        echo('%%% THIS IS THE BEFORE STEP EVENT %%%');
    }

    public function testFailed(\Codeception\Event\FailEvent $e) {
        echo('$$$ THIS IS THE TEST FAILED EVENT $$$');
    }

    public function print(\Codeception\Event\PrintResultEvent $e) {
        echo('&&& THIS IS THE RESULT PRINT AFTER EVENT &&&');
    }
}