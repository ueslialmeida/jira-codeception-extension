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
 *           label: 
 *             - autotest_bug
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
    protected $label;

    /**
     * Issue fields.
     */
    private $failedStep;
    private $testName;
    private $failureMessage;
    private $fileName;
    private $stackTrace;

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
            throw new ExtensionException($this, "Configuration for 'issue type' is missing. Recommended using 'Bug'.");
        }

        $this->issueType = $this->config['issueType'];

        if (!isset($this->config['label'])) {
            throw new ExtensionException($this, "Configuration for 'label' is missing. You may leave it blank but it must be declared.");
        }

        $this->label = $this->config['label'];

        if (!isset($this->config['debugMode'])) {
            throw new ExtensionException($this, "Configuration for 'debug mode' is missing. Possible values are 'true' or 'false'.");
        }

        $this->debug = $this->config['debugMode'];
    }

    /**
     * This method is fired when the event 'step.after' occurs.
     * @param \Codeception\Event\StepEvent $e
     */
    public function afterStep(\Codeception\Event\StepEvent $e)
    {
        if ($e->getStep()->hasFailed()) {
            $this->failedStep = $e->getStep()->toString(self::STRING_LIMIT);
        }
    }

    /**
     * This method is fired when the event 'test.fail' occurs.
     * @param \Codeception\Event\FailEvent $e
     */
    public function testFailed(\Codeception\Event\FailEvent $e)
    {
        if (!$this->debug) {
            $this->stackTrace = $e->getFail()->getTraceAsString();
            $this->failureMessage = $e->getFail()->getMessage();
            $this->fileName = $e->getTest()->getMetadata()->getFilename();
            $this->testName = $e->getTest()->getMetadata()->getName();

            $this->createIssue();
        } else {
            echo ("Debug mode is active, no issue will be created in Jira.\n\n");
        }
    }

    private function createIssue()
    {
        echo ("Creating issue on Jira...\n");

        $jiraAPI = $this->host . '/rest/api/2/issue';

        $issue = json_encode($this->getIssueData());

        $request = curl_init();
        curl_setopt($request, CURLOPT_URL, $jiraAPI);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($request, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($request, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($request, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($this->user . ':' . $this->token),
            'Content-Type: application/json',
        ]);
        curl_setopt($request, CURLOPT_POSTFIELDS, $issue);

        $response = curl_exec($request);
        echo ("Jira response: $response \n\n");
    }

    private function getIssueData()
    {
        $cleanFileName = $this->removeFilePath($this->fileName);

        return [
            'fields' => [
                'project' => ['key' => "$this->projectKey"],
                'summary' => $cleanFileName . ' : ' . $this->testName,
                'description' => "
                Test Name: $this->testName \n
                Failure Message: $this->failureMessage \n
                Failed Step: I $this->failedStep \n
                File Name: $this->fileName \n
                Stack Trace:\n $this->stackTrace",
                'issuetype' => ['name' => $this->issueType],
                'labels' => $this->label,
            ]
        ];
    }

    private function removeFilePath($filePath)
    {
        $pattern = "/[a-zA-Z\d]+\.[php]+/";
        $path = explode('/', $filePath);
        $fileName = implode(preg_grep($pattern, $path));

        return $fileName;
    }
}
