<?php

namespace Codeception\Extension;

use Codeception\Events;
use Codeception\Exception\ExtensionException;

/**
 * @author Uesli Almeida
 * 
 * This extension creates a Jira issue after a test failure.
 *
 * To use this extension a valid Jira configuration is required.
 *
 * Configuration 'codeception.yml' example:
 *
 *      extensions:
 *        enabled:
 *          - Codeception\Extension\JiraExtension
 *        config:
 *          Codeception\Extension\JiraExtension:
 *           host: https://yourdomain.atlassian.net
 *           user: email@mail.com
 *           token: Tg7womaGGFpn9EC16qD3L7T6
 *           projectKey: JE
 *           issueType: Bug
 *           debugMode: false
 */
class JiraExtension extends \Codeception\Extension
{
    const STRING_LIMIT = 1000;

    /**
     * Configuration properties.
     */
    protected $host;
    protected $user;
    protected $token;
    protected $projectKey;
    protected $issueType;
    protected $debug;

    /**
     * Jira issue properties.
     */
    private $failedStep;

    // list events to listen to
    // Codeception\Events constants used to set the event
    public static $events = array(
        Events::STEP_AFTER => 'afterStep',
        Events::TEST_FAIL => 'testFailed',
    );

    public function _initialize()
    {
        if (!isset($this->config['host']) or empty($this->config['host'])) {
            throw new ExtensionException($this, "Configuration for 'host' is missing.");
        }

        $this->host = $this->config['host'];

        if (!isset($this->config['user']) or empty($this->config['user'])) {
            throw new ExtensionException($this, "Configuration for 'user' is missing.");
        }
        
        $this->user = $this->config['user'];

        if (!isset($this->config['token']) or empty($this->config['token'])) {
            throw new ExtensionException($this, "Configuration for 'token' is missing.");
        }

        $this->token = $this->config['token'];

        if (!isset($this->config['projectKey']) or empty($this->config['projectKey'])) {
            throw new ExtensionException($this, "Configuration for 'project key' is missing.");
        }

        $this->projectKey = $this->config['projectKey'];

        if (!isset($this->config['issueType']) or empty($this->config['issueType'])) {
            throw new ExtensionException($this, "Configuration for 'issue type' is missing.");
        }

        $this->issueType = $this->config['issueType'];

        if (!isset($this->config['debugMode'])) {
            throw new ExtensionException($this, "Configuration for 'debug mode' is missing. Possible values are true or false");
        }

        $this->debug = $this->config['debugMode'];
    }

    /**
     * This method is fired when the event 'step.after' occurs.
     * @param \Codeception\Event\StepEvent $e
     */
    public function afterStep(\Codeception\Event\StepEvent $e) {
        if ($e->getStep()->hasFailed()) {
            $this->failedStep = $e->getStep()->toString(self::STRING_LIMIT);
        }
    }

    /**
     * This method is fired when the event 'test.fail' occurs.
     * @param \Codeception\Event\FailEvent $e
     */
    public function testFailed(\Codeception\Event\FailEvent $e) {
        if (!$this->debug) {
            $trace = $e->getFail()->getTraceAsString();
            $message = $e->getFail()->getMessage();
            $fileName = $e->getTest()->getMetadata()->getFilename();
            $testName = $e->getTest()->getMetadata()->getName();

            $this->createIssue($trace, $message, $fileName, $testName);
        }
        else {
            echo "Debug mode is active, no Jira issue will be created.\n\n";
        }
    }

    private function createIssue($trace, $message, $fileName, $testName) {
        echo("CREATING JIRA ISSUE\n");

        $cleanFileName = $this->removeFilePath($fileName);

        $jiraAPI = $this->host . '/rest/api/2/issue';

        $issue = json_encode([
            'fields' => [
            'project' => ['key' => "$this->projectKey"],
            'summary' => $cleanFileName . ' : ' . $testName,
            'description' => "
            Test Name: $testName \n
            Failed Message: $message \n
            Failed Step: I $this->failedStep \n
            File Name: $fileName \n
            Stack Trace:\n $trace",
            'issuetype' => ['name' => $this->issueType],
            'assignee' => ['name' => 'uesli@zoocha.com'],
            ]
        ]);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $jiraAPI);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($this->user . ':' . $this->token),
            'Content-Type: application/json',
        ]);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $issue);

        $response = curl_exec($curl);
        echo "Jira response: $response \n\n";
    }

    private function removeFilePath($filePath) {
        $pattern = "/[a-zA-Z\d]+\.[php]+/";
        $path = explode('/', $filePath);
        $fileName = implode(preg_grep($pattern, $path));
        
        return $fileName;
    }
}